<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6875 V-RGPD) — variables de marque et bloc légal
 * éditables d'une vitrine.
 *
 * - `settings` : allowlist de variables scalaires (brand_name, tagline,
 *   og_image, couleurs) — toute clé inconnue est ignorée, jamais stockée,
 *   pour qu'aucune donnée interne ne puisse un jour fuiter via le DTO public ;
 * - `legal` : allowlist (notice, privacy, contact_email) — bloc mentions
 *   légales / politique de confidentialité éditable par le tenant.
 *
 * Les clés non fournies sont conservées (sémantique PATCH par fusion).
 * Invalide le cache public (le rendu change) et journalise l'audit.
 */
final class UpdateShowcaseSettingsAction
{
    /** Allowlist des variables scalaires de `settings` exposées au public. */
    private const ALLOWED_SETTING_KEYS = ['brand_name', 'tagline', 'og_image', 'font_family', 'radius'];

    /** Allowlist des sous-clés de `settings.colors`. */
    private const ALLOWED_COLOR_KEYS = ['primary', 'accent', 'surface', 'on_primary'];

    /** Allowlist des clés du bloc `legal`. */
    private const ALLOWED_LEGAL_KEYS = ['notice', 'privacy', 'contact_email'];

    public function __construct(private readonly ShowcasePublicCache $cache) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(CompanyShowcase $showcase, array $payload, ?int $actorId = null): CompanyShowcase
    {
        if (array_key_exists('settings', $payload)) {
            if (! is_array($payload['settings'])) {
                throw ValidationException::withMessages([
                    'settings' => [(string) __('showcase.settings_must_be_object')],
                ]);
            }

            /** @var array<string, mixed> $incomingSettings */
            $incomingSettings = $payload['settings'];

            $showcase->settings = $this->mergeSettings($showcase->settings ?? [], $incomingSettings);
        }

        if (array_key_exists('legal', $payload)) {
            if (! is_array($payload['legal'])) {
                throw ValidationException::withMessages([
                    'legal' => [(string) __('showcase.legal_must_be_object')],
                ]);
            }

            /** @var array<string, mixed> $incomingLegal */
            $incomingLegal = $payload['legal'];

            $showcase->legal = $this->mergeLegal($showcase->legal ?? [], $incomingLegal);
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
            'old_values' => [],
            'new_values' => ['settings_updated' => true],
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
            /** @var array<string, mixed> $colors */
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
