<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Résolution des messages du domaine Showcase (BC-27 SHOWCASE, #6874).
 *
 * Les messages utilisateur passent par les catalogues `api/lang/{fr,en,ar,tr}
 * /showcase.php` (garde PA2-I18N-007 : aucune chaîne accentuée en dur). Le
 * Domain reste toutefois utilisable hors application Laravel (tests unitaires
 * du contrat de sections) : quand le traducteur n'est pas disponible, un repli
 * technique NON accentué est retourné — jamais une chaîne française en dur.
 */
final class ShowcaseMessage
{
    /**
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace, string $fallback): string
    {
        if (function_exists('app') && app()->bound('translator')) {
            return (string) __('showcase.'.$key, $replace);
        }

        return $fallback;
    }
}
