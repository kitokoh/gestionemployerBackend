<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6868 V-THEMES) — sélection du thème de la vitrine.
 *
 * Le thème doit être un thème v1 connu (allowlist `ShowcaseThemeRegistry`) ;
 * le contenu des sections n'est pas modifié (contenu séparé de la
 * présentation). Invalide le cache public (le rendu change) et journalise
 * l'audit.
 */
final class UpdateShowcaseThemeAction
{
    public function __construct(private readonly ShowcasePublicCache $cache) {}

    public function execute(CompanyShowcase $showcase, string $theme, ?int $actorId = null): CompanyShowcase
    {
        if (! ShowcaseThemeRegistry::isKnown($theme)) {
            throw ValidationException::withMessages([
                'theme' => [(string) __('showcase.theme_invalid')],
            ]);
        }

        $showcase->theme = $theme;
        $showcase->save();

        $this->cache->forget($showcase->slug);

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.theme_updated',
            'auditable_type' => CompanyShowcase::class,
            'auditable_id' => $showcase->id,
            'old_values' => [],
            'new_values' => ['theme' => $theme],
        ]);

        return $showcase;
    }
}
