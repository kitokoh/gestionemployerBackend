<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use App\Modules\Catalog\Domain\Support\CatalogPriceFormatter;
use RuntimeException;
use Tests\TestCase;

/**
 * BC-28 CATALOG (C-CURRENCY #6886) — formatage prix minor units via intl :
 * aucune perte ni arrondi flottant, décimales canoniques par devise
 * (XOF/XAF 0 ; DZD/MAD/EUR/USD 2).
 */
class CatalogPriceFormatterTest extends TestCase
{
    public function test_formats_minor_units_with_two_decimals(): void
    {
        // 1 250 000 minor units DZD = 12 500,00 DZD — jamais « 12 500,000… ».
        $formatted = $this->normalize(CatalogPriceFormatter::format(1_250_000, 'DZD', 'fr_FR'));
        $this->assertStringContainsString('12 500,00', $formatted);
        $this->assertStringContainsString('DZD', $formatted);
    }

    public function test_zero_fraction_currency_has_no_decimals(): void
    {
        // XOF : 0 décimale canonique — 1 250 000 minor = 1 250 000 XOF.
        $formatted = $this->normalize(CatalogPriceFormatter::format(1_250_000, 'XOF', 'fr_FR'));
        $this->assertStringNotContainsString(',', $formatted);
        $this->assertStringContainsString('1 250 000', $formatted);
    }

    public function test_small_amount_keeps_exact_cents(): void
    {
        // 1999 minor EUR = 19,99 € (pas d'arrondi à 20,00).
        $formatted = $this->normalize(CatalogPriceFormatter::format(1999, 'EUR', 'fr_FR'));
        $this->assertStringContainsString('19,99', $formatted);
        $this->assertStringNotContainsString('20', $formatted);
    }

    public function test_minor_units_below_one_major(): void
    {
        // 5 minor EUR = 0,05 €.
        $formatted = $this->normalize(CatalogPriceFormatter::format(5, 'EUR', 'fr_FR'));
        $this->assertStringContainsString('0,05', $formatted);
    }

    /**
     * ICU utilise des espaces insécables/narrow nbsp (U+00A0/U+202F) comme
     * séparateurs de milliers en fr_FR — on les normalise en espaces simples.
     */
    private function normalize(string $value): string
    {
        return (string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $value);
    }

    public function test_invalid_currency_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        CatalogPriceFormatter::format(100, 'NOTACURRENCY');
    }
}
