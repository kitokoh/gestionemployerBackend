<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

use NumberFormatter;
use RuntimeException;

/**
 * Formatage des prix indicatifs du catalogue B2B (BC-28 CATALOG,
 * C-CURRENCY #6886 — spec §8 « affichage formaté (intl) »).
 *
 * Prix stockés en **minor units** (entier, jamais de flottant en base).
 * La conversion major/minor est faite en ARITHMÉTIQUE ENTIÈRE
 * (intdiv + modulo, pas de division flottante) puis la valeur décimale est
 * passée à `NumberFormatter::CURRENCY` d'intl (ICU), qui applique les
 * décimales canoniques de la devise (XOF/XAF → 0 ; DZD/MAD/EUR/USD → 2).
 *
 * Borne documentée : le passage par float pour intl est exact pour les
 * montants < 2^53 minor units (~90 000 Md de centimes) — très au-delà du
 * plafond métier (`price_minor` ≤ 9,2e18 validé en entrée est théorique,
 * aucun produit réel n'approche 2^53).
 */
final class CatalogPriceFormatter
{
    public static function format(int $minorUnits, string $currency, string $locale = 'fr_FR'): string
    {
        $currency = strtoupper($currency);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new RuntimeException("Devise invalide pour le formatage : {$currency}");
        }

        // Minor units → major en arithmétique entière (pas de flottant).
        // L'exposant dépend de la devise (XOF/XAF = 0 décimale : minor ==
        // major ; DZD/MAD/EUR/USD = 2 : division par 100 entière).
        $exponent = CatalogPricePolicy::currencyExponent($currency);
        $factor = 10 ** $exponent;
        $whole = intdiv($minorUnits, $factor);
        $fraction = $exponent === 0 ? 0 : $minorUnits % $factor;
        $decimal = $fraction === 0
            ? (string) $whole
            : $whole.'.'.str_pad((string) $fraction, $exponent, '0', STR_PAD_LEFT);

        // Le constructeur lève une IntlException si le locale est invalide
        // (PHP ≥ 8) — jamais null, pas de garde instanceof nécessaire.
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        $formatted = $formatter->formatCurrency((float) $decimal, $currency);

        if (! is_string($formatted)) {
            throw new RuntimeException("Échec du formatage intl pour {$minorUnits} {$currency}");
        }

        return $formatted;
    }
}
