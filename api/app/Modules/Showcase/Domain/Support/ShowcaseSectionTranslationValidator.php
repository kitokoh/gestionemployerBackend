<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use Illuminate\Validation\ValidationException;

/**
 * Validation des surcouches multilingues d'une section (BC-27 SHOWCASE, #6874).
 *
 * `translations` = `{ "<locale>": <contenu partiel> }` :
 *   - les clés de locale doivent appartenir à
 *     {@see ShowcaseSectionSchemaRegistry::SUPPORTED_LOCALES} ;
 *   - la locale de référence (`fr`) est portée par `content` et n'a pas sa
 *     place dans `translations` (source unique) ;
 *   - chaque surcouche est validée contre le MÊME JSON Schema de section que
 *     `content`, en mode partiel (les champs non traduits retombent sur le
 *     contenu de référence au rendu) ;
 *   - les erreurs remontent en 422 au format Laravel, indexées
 *     `translations.<locale>.<chemin>` — directement consommables par
 *     l'éditeur V-EDITOR #6870.
 */
final class ShowcaseSectionTranslationValidator
{
    public function __construct(private readonly ShowcaseSectionSchemaValidator $schemaValidator) {}

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, mixed> Surcouches normalisées (clés connues uniquement).
     *
     * @throws ValidationException
     */
    public function validateOrFail(string $type, array $translations): array
    {
        $errors = $this->validate($type, $translations);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $translations;
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, list<string>> Erreurs indexées par chemin (style Laravel).
     */
    public function validate(string $type, array $translations): array
    {
        $errors = [];

        foreach ($translations as $locale => $overlay) {
            $key = 'translations.'.(is_string($locale) ? $locale : (string) $locale);

            if (! is_string($locale) || ! ShowcaseSectionSchemaRegistry::isSupportedLocale($locale)) {
                $errors[$key] = [ShowcaseMessage::get('section_locale_unsupported', [
                    'locale' => is_string($locale) ? $locale : (string) $locale,
                    'locales' => implode('/', ShowcaseSectionSchemaRegistry::supportedLocales()),
                ], 'Unsupported section locale: '.(is_string($locale) ? $locale : (string) $locale).'.')];

                continue;
            }

            if ($locale === ShowcaseSectionSchemaRegistry::DEFAULT_LOCALE) {
                $errors[$key] = [ShowcaseMessage::get('section_locale_is_default', [
                    'locale' => $locale,
                ], 'Locale '.$locale.' belongs to the reference content.')];

                continue;
            }

            if (! is_array($overlay)) {
                $errors[$key] = [ShowcaseMessage::get('section_translation_not_object', ['locale' => $locale], 'The translation must be an object.')];

                continue;
            }

            foreach ($this->schemaValidator->validate($type, $overlay, true) as $path => $messages) {
                $suffix = str_starts_with((string) $path, 'content.')
                    ? substr((string) $path, strlen('content.'))
                    : (string) $path;

                $errors[$key.($suffix !== '' ? '.'.$suffix : '')] = $messages;
            }
        }

        return $errors;
    }
}
