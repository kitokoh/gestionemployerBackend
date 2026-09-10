<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Bloc légal par défaut d'une vitrine publiée (BC-27 SHOWCASE, V-RGPD #6875).
 *
 * Une vitrine publiée sans bloc légal édité expose ces textes génériques
 * (mentions légales + politique de confidentialité) : la page publique n'est
 * jamais publiée sans mentions. Aucun cookie tiers n'est posé par la vitrine
 * — la politique de cookies exposée au public est déclarative
 * (`cookies.third_party = false`), alignée sur le protocole P05 §5 (pas de
 * tracker par défaut).
 *
 * Le tenant peut surcharger `notice`, `privacy` et `contact_email` via
 * `PUT /showcase/settings` (champ `legal`).
 */
final class ShowcaseLegalDefaults
{
    /**
     * Texte de mentions légales générique (fallback documenté).
     */
    public static function notice(): string
    {
        return "Leopardo RH — site vitrine de l'entreprise. Éditeur : le titulaire du compte Leopardo RH. "
            .'Hébergement : infrastructures du titulaire du compte. Contact : utilisez le formulaire de contact de cette page.';
    }

    /**
     * Politique de confidentialité générique (fallback documenté).
     */
    public static function privacy(): string
    {
        return 'Ce site vitrine ne collecte aucune donnée personnelle de visiteur et ne dépose aucun cookie tiers. '
            ."Les données affichées (catalogue, actualités) sont publiées par l'entreprise titulaire du compte. "
            ."Pour exercer vos droits, contactez l'entreprise via le formulaire de contact.";
    }

    /**
     * Bloc légal résolu : surcharges du tenant (scalaires uniquement) sinon
     * défauts ci-dessus. Ne retourne jamais de clé interne.
     *
     * @param  array<string, mixed>|null  $legal
     * @return array{notice: string, privacy: string, contact_email: string|null}
     */
    public static function resolved(?array $legal): array
    {
        $notice = $legal['notice'] ?? null;
        $privacy = $legal['privacy'] ?? null;
        $contactEmail = $legal['contact_email'] ?? null;

        return [
            'notice' => is_string($notice) && trim($notice) !== '' ? $notice : self::notice(),
            'privacy' => is_string($privacy) && trim($privacy) !== '' ? $privacy : self::privacy(),
            'contact_email' => is_string($contactEmail) && trim($contactEmail) !== '' ? $contactEmail : null,
        ];
    }
}
