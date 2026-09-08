<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Section typée de la vitrine d'un tenant (BC-27 SHOWCASE, #6866).
 *
 * Une section est un bloc ordonné (`position`) de la page publique : son
 * `content` est validé à l'écriture contre le JSON Schema versionné de son
 * `type` (`schema_version`, contrat ShowcaseSectionSchemas). Tenant-scoped
 * (`company_id`) et rattachée à la vitrine du tenant (`showcase_id` →
 * company_showcases), sans FK (conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property int $showcase_id
 * @property string $type
 * @property int $schema_version
 * @property array<string, mixed> $content
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class ShowcaseSection extends Model
{
    protected $table = 'showcase_sections';

    protected $fillable = [
        'company_id',
        'showcase_id',
        'type',
        'schema_version',
        'content',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'content' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CompanyShowcase, $this>
     */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(CompanyShowcase::class, 'showcase_id');
    }

    /**
     * Ordre canonique de la page : position croissante, puis id (stabilité
     * du re-tri). Scope par défaut du modèle.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * Contrat de schéma versionné de la section (pour l'éditeur #6870 et la
     * re-validation défensive au rendu public #6867).
     *
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return ShowcaseSectionSchemas::all()[$this->type]
            ?? ['type' => 'object', 'additionalProperties' => true];
    }
}
