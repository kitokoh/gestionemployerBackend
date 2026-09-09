<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use Illuminate\Foundation\Http\FormRequest;
use App\Modules\Catalog\Domain\Support\CatalogUnitsCurrencies;
use Illuminate\Validation\Rule;

/**
 * Mise à jour d'un produit du catalogue B2B (BC-28 CATALOG, #6881).
 *
 * Slug unique par tenant, hors produit courant.
 */
class UpdateCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $actor */
        $actor = $this->user();

        $product = $this->route('product');
        $productId = $product instanceof CatalogProduct ? (int) $product->getKey() : (int) $product;
        $statuses = array_map(
            static fn (CatalogProductStatus $s): string => $s->value,
            CatalogProductStatus::cases()
        );

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'nullable',
                'alpha_dash',
                'max:160',
                Rule::unique('catalog_products', 'slug')
                    ->where('company_id', (string) $actor->company_id)
                    ->ignore($productId),
            ],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('catalog_categories', 'id')
                    ->where('company_id', (string) $actor->company_id),
            ],
            'description' => ['nullable', 'string', 'max:10000'],
            'price_minor' => ['required', 'integer', 'min:0', 'max:9223372036854775807'],
            // #6886 : devise ISO supportée (registre pays + EUR/USD) — défaut = devise du tenant (contrôleur).
            'currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'in:'.implode(',', CatalogUnitsCurrencies::supportedCurrencies())],
            'unit' => ['nullable', 'string', 'max:20', 'in:'.implode(',', CatalogUnitsCurrencies::supportedUnits())],
            'status' => ['nullable', Rule::in($statuses)],
            'meta' => ['nullable', 'array'],
        ];
    }
}
