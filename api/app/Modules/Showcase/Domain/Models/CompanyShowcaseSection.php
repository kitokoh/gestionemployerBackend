<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Section ordonnée d'une vitrine publique (BC-27 SHOWCASE, #6866).
 *
 * Une vitrine = liste ordonnée de sections typées ; `content` est un JSON
 * validé par type contre le JSON Schema versionné
 * (`ShowcaseSectionSchemaRegistry`, `schema_version`) — jamais stocké brut.
 * Tenant-scoped (`company_id`), `showcase_id` sans FK (conventions
 * migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property int $showcase_id
 * @property ShowcaseSectionType $type
 * @property array<string, mixed> $content
 * @property int $sort_order
 * @property int $schema_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 * @method static Builder<static> ordered()
 *
 * @mixin Builder<static>
 */
class CompanyShowcaseSection extends Model
{
    use BelongsToCompany;

    protected $table = 'company_showcase_sections';

    protected $fillable = [
        'company_id',
        'showcase_id',
        'type',
        'content',
        'sort_order',
        'schema_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShowcaseSectionType::class,
            'content' => 'array',
            'sort_order' => 'integer',
            'schema_version' => 'integer',
        ];
    }

    public function showcase(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CompanyShowcase::class, 'showcase_id');
    }

    /**
     * Ordre canonique d'une vitrine : sort_order puis id (stable).
     *
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
