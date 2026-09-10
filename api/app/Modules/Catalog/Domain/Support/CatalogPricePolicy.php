<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

/**
 * Politique prix/devises/unités du catalogue B2B (BC-28 CATALOG,
 * C-CURRENCY #6886 — spec §8).
 *
 * Devises ISO 4217 acceptées (zones produits) et unités canoniques v1 :
 * whitelists strictes pour la validation (jamais de valeur libre) MAIS
 * sur-enrichissables par configuration (`catalog.currencies` /
 * `catalog.units`, env `CATALOG_CURRENCIES` / `CATALOG_UNITS`, csv) — la
 * config est chargée quand elle existe (fichier apporté par C-PUBLIC
 * #6882/`api/config/catalog.php`) ; tant qu'elle est absente, les listes
 * canoniques ci-dessous font foi. Aucun paiement en ligne v1 (documenté
 * spec §8 ; passerelle plus tard via BC-21/BC-08).
 */
final class CatalogPricePolicy
{
    /** Devises ISO 4217 par défaut (zones produits Leopardo). */
    public const CURRENCIES = [
        'XOF',
        'XAF',
        'DZD',
        'MAD',
        'EUR',
        'USD',
    ];

    /** Unités canoniques v1 (code ASCII stable, i18n côté affichage). */
    public const UNITS = [
        'piece',
        'kg',
        'tonne',
        'm',
        'm2',
        'm3',
        'hour',
        'day',
        'lot',
    ];

    /** Devises sans unité fractionnaire (0 décimale canonique ISO 4217). */
    private const ZERO_DECIMAL_CURRENCIES = ['XOF', 'XAF'];

    /** Exposant canonique de la devise (2 sauf 0-décimale). */
    public static function currencyExponent(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }

    /**
     * @return list<string>
     */
    public static function allowedCurrencies(): array
    {
        $configured = config('catalog.currencies');

        return is_array($configured) && $configured !== []
            ? array_values(array_map('strval', $configured))
            : self::CURRENCIES;
    }

    /**
     * @return list<string>
     */
    public static function allowedUnits(): array
    {
        $configured = config('catalog.units');

        return is_array($configured) && $configured !== []
            ? array_values(array_map('strval', $configured))
            : self::UNITS;
    }
}
