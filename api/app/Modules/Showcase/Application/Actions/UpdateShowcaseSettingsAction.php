<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6868 V-THEMES, #6875 V-RGPD) — réglages de marque et
 * bloc légal de la vitrine.
 *
 * - `theme` : identifiant du thème v1 (ShowcaseThemeRegistry) ;
 * - `settings` : variables de marque en **allowlist** (brand_name, tagline,
 *   font_family, radius, colors.primary/accent/surface/on_primary) — toute
 *   autre clé est ignorée (jamais stockée) pour éviter d'exposer un jour une
 *   donnée interne via le DTO public ;
 * - `legal` : mentions légales / politique de confidentialité / e-mail de
 *   contact (scalaires) ;
 * - invalide le cache public (le rendu public change) et journalise l'audit.
 *
 * Les clés non fournies sont conservées (PATCH sémantique par fusion).
 */
final class UpdateShowcaseSettingsAction
{
    /** Allowlist des clés scalaires de `settings` exposées au public. */
    private const ALLOWED_SETTING_KEYS = ['brand_name', 'tagline', 'font_family', 'radius'];

    /** Allowlist des sous-clés de `settings.colors`. */
    private const ALLOWED_COLOR_KEYS = ['primary', 'accent', 'surface', 'on_primary'];

    /** Allowlist des clés de `legal`. */
    private const ALLOWED_LEGAL_KEYS = ['notice', 'privacy', 'contact_email'];

    public function __construct(private readonly ShowcasePublicCache $cache) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(CompanyShowcase $showcase, array $payload, ?int $actorId = null): CompanyShowcase
    {
        $oldTheme = $showcase->theme;

        if (array_key_exists('theme', $payload)) {
            $theme = $payload['theme'];

            if (! is_string($theme) || ! ShowcaseThemeRegistry::exists($theme)) {
                throw ValidationException::withMessages([
                    'theme' => __('showcase.theme_unknown', ['themes' => implode(', ', ShowcaseThemeRegistry::ids())]),
                ]);
            }

            $showcase->theme = $theme;
        }

        if (array_key_exists('settings', $payload)) {
            if (! is_array($payload['settings'])) {
                throw ValidationException::withMessages(['settings' => __('showcase.settings_must_be_object')]);
            }

            $showcase->settings = $this->mergeSettings($showcase->settings ?? [], $payload['settings']);
        }

        if (array_key_exists('legal', $payload)) {
            if (! is_array($payload['legal'])) {
                throw ValidationException::withMessages(['legal' => __('showcase.legal_must_be_object')]);
            }

            $showcase->legal = $this->mergeLegal($showcase->legal ?? [], $payload['legal']);
        }

        $showcase->save();

        $this->cache->forget($showcase->slug);

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.settings_updated',
            'auditable_type' => CompanyShowcase::class,
            'auditable_id' => $showcase->id,
            'old_values' => ['theme' => $oldTheme],
            'new_values' => ['theme' => $showcase->theme],
        ]);

        return $showcase;
    }

    /**
     * Fusionne les variables de marque en filtrant par allowlist.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeSettings(array $current, array $incoming): array
    {
        foreach (self::ALLOWED_SETTING_KEYS as $key) {
            if (array_key_exists($key, $incoming) && is_string($incoming[$key])) {
                $current[$key] = $incoming[$key];
            }
        }

        if (isset($incoming['colors']) && is_array($incoming['colors'])) {
            $colors = is_array($current['colors'] ?? null) ? $current['colors'] : [];

            foreach (self::ALLOWED_COLOR_KEYS as $key) {
                if (isset($incoming['colors'][$key]) && is_string($incoming['colors'][$key])) {
                    $colors[$key] = $incoming['colors'][$key];
                }
            }

            $current['colors'] = $colors;
        }

        return $current;
    }

    /**
     * Fusionne le bloc légal en filtrant par allowlist (scalaires).
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeLegal(array $current, array $incoming): array
    {
        foreach (self::ALLOWED_LEGAL_KEYS as $key) {
            if (array_key_exists($key, $incoming) && is_string($incoming[$key])) {
                $current[$key] = $incoming[$key];
            }
        }

        return $current;
    }
}
