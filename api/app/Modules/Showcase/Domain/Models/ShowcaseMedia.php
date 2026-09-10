<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Média d'une vitrine publique (BC-27 SHOWCASE, #6872 V-MEDIA).
 *
 * Le fichier vit sur un disque Laravel (`disk` + `path` — disque `public`,
 * cf. `config/filesystems.php`) ; la référence exposée/éditée est le `uuid`
 * stable (jamais un chemin, jamais l'id interne) :
 *   - logo de marque   → `company_showcases.settings.logo_id` ;
 *   - visuel de section → `content.image_id` du JSON Schema.
 *
 * Tenant-scoped (`company_id`, BelongsToCompany) — un média d'un autre tenant
 * est invisible (fail-closed #3727).
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $showcase_id
 * @property int|null $section_id
 * @property string $uuid
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property string|null $checksum
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class ShowcaseMedia extends Model
{
    use BelongsToCompany;

    public const KIND_LOGO = 'logo';

    public const KIND_IMAGE = 'image';

    protected $table = 'company_showcase_media';

    protected $fillable = [
        'company_id',
        'showcase_id',
        'section_id',
        'uuid',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'checksum',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'showcase_id' => 'integer',
            'section_id' => 'integer',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CompanyShowcase, $this>
     */
    public function showcase(): BelongsTo
    {
        return $this->belongsTo(CompanyShowcase::class, 'showcase_id');
    }

    public static function isLogoKind(string $kind): bool
    {
        return $kind === self::KIND_LOGO;
    }
}
