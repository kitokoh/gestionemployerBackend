<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Demande de devis/contact B2B reçue via le formulaire public du catalogue
 * (BC-28 CATALOG, C-LEAD #6884).
 *
 * Tenant-scoped (`company_id` du tenant producteur). Données acheteur
 * minimisées (RGPD spec §9) : société + email + message, consentement
 * explicite horodaté (`consent_at`), conservation bornée (`retention_until`,
 * purge C-RGPD #6889), IP stockée hashée. `product_name` = instantané à la
 * réception (le produit peut être dépublié ensuite). Statut whitelisté
 * (CatalogInquiryStatus) — écrit `new` à la création.
 *
 * @property int $id
 * @property string $company_id
 * @property string $product_slug
 * @property string $product_name
 * @property int|null $quantity
 * @property string $company_name
 * @property string $email
 * @property string|null $message
 * @property string|null $notes
 * @property CatalogInquiryStatus $status
 * @property Carbon|null $consent_at
 * @property Carbon|null $retention_until
 * @property string|null $ip_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class CatalogInquiry extends Model
{
    use BelongsToCompany;

    protected $table = 'catalog_inquiries';

    protected $fillable = [
        'company_id',
        'product_slug',
        'product_name',
        'quantity',
        'company_name',
        'email',
        'message',
        'notes',
        'status',
        'consent_at',
        'retention_until',
        'ip_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'status' => CatalogInquiryStatus::class,
            'consent_at' => 'datetime',
            'retention_until' => 'date',
        ];
    }
}
