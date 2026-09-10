<?php

declare(strict_types=1);

/**
 * Catalogue i18n du module Showcase (BC-27 SHOWCASE, #6875 V-RGPD).
 *
 * Parité stricte fr/en/ar/tr (garde check-i18n-catalog-parity) : toute clé
 * ajoutée ici doit l'être dans les 4 locales. Aucune chaîne user-facing
 * accentuée n'est codée en dur dans les contrôleurs/actions (PA2-I18N-007).
 */
return [
    'legal_title' => 'Mentions légales',
    'legal_privacy_title' => 'Politique de confidentialité',
    'legal_notice_default' => "Éditeur : l'entreprise titulaire de cette vitrine, via la plateforme Leopardo RH. Hébergement : infrastructures du titulaire du compte. Contact : utilisez le formulaire de contact de cette page.",
    'legal_privacy_default' => "Cette vitrine ne dépose aucun cookie tiers et ne collecte aucune donnée de navigation. Les données transmises via le formulaire de contact sont utilisées uniquement pour répondre à votre demande et sont conservées de façon limitée. Pour exercer vos droits, contactez l'entreprise via le formulaire de contact.",
    'cookie_notice' => 'Cette vitrine ne dépose aucun cookie tiers ni traceur publicitaire. Seule une préférence locale est mémorisée dans votre navigateur.',
    'cookie_accept' => "J'ai compris",
    'contact_title' => 'Nous contacter',
    'contact_name' => 'Votre nom',
    'contact_email' => 'Votre e-mail',
    'contact_message' => 'Votre message',
    'contact_consent_label' => "J'accepte que mes données soient utilisées pour répondre à ma demande.",
    'contact_consent_required' => 'Le consentement est obligatoire pour envoyer le message.',
    'contact_submit' => 'Envoyer',
    'contact_success' => 'Message envoyé. Nous vous répondrons rapidement.',
    'contact_error' => "L'envoi a échoué. Merci de réessayer.",
    'contact_notification_title' => 'Nouveau message sur la vitrine',
    'contact_notification_body' => 'Un visiteur (:name) a envoyé un message via le formulaire de contact de votre vitrine.',
    'not_found_title' => 'Page introuvable',
    'not_found_body' => "Cette vitrine n'existe pas ou n'est pas publiée.",
    'settings_must_be_object' => 'Les réglages doivent être un objet.',
    'legal_must_be_object' => 'Le bloc légal doit être un objet.',
    'media_invalid_type' => 'Type de fichier non autorisé (PNG, JPEG, WebP ; SVG pour le logo uniquement).',
    'media_too_large' => 'Fichier trop volumineux (2 Mo max pour le logo, 5 Mo pour une image).',
    'media_section_not_found' => "La section ciblée n'appartient pas à cette vitrine.",
    'theme_industrie' => 'Industrie',
    'theme_service' => 'Service',
    'theme_commerce' => 'Commerce',
    'theme_invalid' => 'Thème inconnu.',
    'section_type_unknown' => 'Type de section inconnu : :type.',
    'section_field_required' => 'Le champ :field est requis.',
    'section_locale_unsupported' => 'Locale :locale non supportée (locales autorisées : :locales).',
    'section_locale_is_default' => "La locale :locale est portée par le contenu de référence (content), jamais par translations.",
    'section_translation_not_object' => 'La traduction :locale doit être un objet.',
];
