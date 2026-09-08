<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaValidator;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6866) — mise à jour d'une section (contenu et/ou type).
 *
 * Le remplacement de type re-valide le contenu contre le JSON Schema du
 * nouveau type ; le `content` fourni est TOUJOURS validé avant persistance
 * (jamais de contenu brut en base). L'ordre (`sort_order`) n'est pas modifié
 * ici — use case ReorderShowcaseSectionsAction. Cache public invalidé si la
 * vitrine est publiée.
 */
final class UpdateShowcaseSectionAction
{
    public function __construct(
        private readonly ShowcaseSectionSchemaValidator $validator,
        private readonly ShowcasePublicCache $publicCache,
    ) {}

    /**
     * @param  array<string, mixed>|null  $content
     */
    public function execute(CompanyShowcase $showcase, CompanyShowcaseSection $section, ?string $type, ?array $content = null): CompanyShowcaseSection
    {
        $nextType = $type ?? $section->type->value;

        if (! ShowcaseSectionSchemaRegistry::isKnownType($nextType)) {
            throw ValidationException::withMessages([
                'type' => [sprintf('Type de section inconnu : « %s ».', $nextType)],
            ]);
        }

        // PATCH partiel : content absent → contenu existant conservé (re-validé
        // contre le type cible — un changement de type incompatible → 422).
        $nextContent = $content ?? ($section->content ?? []);
        $nextContent = $this->validator->validateOrFail($nextType, $nextContent);

        $section->type = ShowcaseSectionType::from($nextType);
        $section->content = $nextContent;
        $section->schema_version = ShowcaseSectionSchemaRegistry::SCHEMA_VERSION;
        $section->save();

        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }

        return $section;
    }
}
