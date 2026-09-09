<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\Catalog\Domain\Enums\CatalogQuoteStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Demande de devis/contact B2B reçue sur le catalogue public
 * (BC-28 CATALOG, #6884/#6885).
 *
 * Instantané produit (slug + nom) au moment de la demande — pas de FK
 * (conventions tenant §2.6) : un produit supprimé ensuite laisse la
 * demande lisible. Statut workflow `new|contacted|quote_sent|closed|lost`
 * (enum PHP), notes internes réservées au back-office, trace consentement
 * RGPD horodatée. Tenant-scoped (`company_id`), `reference` UUID globale.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property string $product_slug
 * @property string $product_name
 * @property int|null $quantity
 * @property string $buyer_company
 * @property string $contact_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $message
 * @property CatalogQuoteStatus $status
 * @property string|null $internal_notes
 * @property Carbon|null $consented_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class CatalogQuote extends Model
{
    use BelongsToCompany;

    protected $table = 'catalog_quote_requests';

    protected $fillable = [
        'company_id',
        'reference',
        'product_slug',
        'product_name',
        'quantity',
        'buyer_company',
        'contact_name',
        'email',
        'phone',
        'message',
        'status',
        'internal_notes',
        'consented_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CatalogQuoteStatus::class,
            'consented_at' => 'datetime',
        ];
    }
}
