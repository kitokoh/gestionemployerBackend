<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

use App\Support\CountryDefaults;

/**
 * Devises & unités du catalogue B2B (BC-28 CATALOG, #6886 — C-CURRENCY).
 *
 * Montants indicatifs stockés en **minor units** (entier, jamais de
 * flottant — spec SOLUTION_CATALOGUE_B2B.md §4) + devise ISO 4217.
 * Pas de paiement en ligne en v1 (BC-21/BC-08 plus tard).
 *
 * Devises acceptées v1 : celles des pays supportés (registre
 * CountryDefaults #1867) + EUR/USD (acheteurs internationaux) — liste
 * canonique unique, dérivée (pas de copie qui dérive).
 * Unités acceptées v1 : liste blanche explicite (pièce, masse, volume,
 * longueur, surface, temps, conditionnement…).
 */
final class CatalogUnitsCurrencies
{
    /** Devises hors registre pays supportés, autorisées pour les acheteurs internationaux. */
    private const EXTRA_CURRENCIES = ['EUR', 'USD'];

    /** Unités v1 — liste blanche (spec §8). Ajout = PR + test. */
    private const UNITS = [
        'piece',
        'kg',
        'tonne',
        'liter',
        'm',
        'm2',
        'm3',
        'hour',
        'day',
        'service',
        'box',
        'set',
        'lot',
        'palette',
    ];

    /** @var list<string>|null */
    private static ?array $currencyCache = null;

    /**
     * Devises supportées (pays du registre #1867 + EUR/USD), triées.
     *
     * @return list<string>
     */
    public static function supportedCurrencies(): array
    {
        if (self::$currencyCache !== null) {
            return self::$currencyCache;
        }

        $currencies = self::EXTRA_CURRENCIES;
        foreach (CountryDefaults::all() as $defaults) {
            $currencies[] = strtoupper((string) $defaults['currency']);
        }

        $currencies = array_values(array_unique($currencies));
        sort($currencies);

        return self::$currencyCache = $currencies;
    }

    public static function isSupportedCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::supportedCurrencies(), true);
    }

    /**
     * @return list<string>
     */
    public static function supportedUnits(): array
    {
        return self::UNITS;
    }

    public static function isSupportedUnit(string $unit): bool
    {
        return in_array($unit, self::UNITS, true);
    }

    public static function normalizeCurrency(?string $currency): ?string
    {
        if ($currency === null) {
            return null;
        }

        $normalized = strtoupper(trim($currency));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Représentation d'affichage canonique d'un montant minor units :
     * « majeures » à 2 décimales + code devise — calculée en ENTIERS
     * (intdiv/mod), jamais de flottant (pas d'arrondi bancaire).
     *
     * L'affichage localisé final (séparateurs intl, symbole) appartient aux
     * surfaces (front) — ce helper garantit une valeur canonique stable.
     */
    public static function formatMinorUnits(int $minorUnits, string $currency): string
    {
        $major = intdiv($minorUnits, 100);
        $cents = $minorUnits % 100;
        $majorFormatted = number_format($major, 0, '.', ' ');

        return sprintf('%s.%02d %s', $majorFormatted, $cents, strtoupper($currency));
    }
}
