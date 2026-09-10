<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use Illuminate\Validation\ValidationException;

/**
 * Validation du `content` d'une section vitrine contre son JSON Schema
 * (BC-27 SHOWCASE, #6866).
 *
 * Interprète le sous-ensemble de JSON Schema (draft-07) supporté par
 * {@see ShowcaseSectionSchemaRegistry} : `type`, `properties`, `required`,
 * `additionalProperties`, `items`, `maxLength`, `maxItems`, `minItems`.
 * Rejet `additionalProperties: false` → clés inconnues refusées (anti-dérive
 * du contrat et anti-XSS par construction : le contenu rendu est un
 * sous-ensemble connu du thème, cf. V-THEMES #6868).
 *
 * L'erreur remonte en 422 standard (ValidationException) — la même forme
 * que les erreurs de requête Laravel, consommable par l'éditeur (V-EDITOR
 * #6870).
 */
final class ShowcaseSectionSchemaValidator
{
    /**
     * Valide le contenu et lève une ValidationException si invalide.
     *
     * En mode `$partial` (surcouche de traduction, #6874), les contraintes de
     * complétude (`required`, `minItems`) sont ignorées : une traduction peut
     * ne couvrir qu'une partie des champs localisables, les autres retombant
     * sur le contenu de référence au rendu. Les contraintes de forme
     * (`type`, `additionalProperties`, `maxLength`, `maxItems`) restent
     * appliquées.
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed> Contenu normalisé (clés connues uniquement).
     *
     * @throws ValidationException
     */
    public function validateOrFail(string $type, array $content, bool $partial = false): array
    {
        $errors = $this->validate($type, $content, $partial);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, list<string>> Erreurs indexées par chemin (style Laravel).
     */
    public function validate(string $type, array $content, bool $partial = false): array
    {
        $schema = ShowcaseSectionSchemaRegistry::schemaFor($type);

        if ($schema === null) {
            return ['type' => [$this->message('section_type_unknown', ['type' => $type], 'Unknown section type: '.$type.'.')]];
        }

        $errors = [];
        $this->checkNode($schema, $content, 'content', $errors, $partial);

        return $errors;
    }

    /**
     * Message d'erreur localisé (clé `showcase.*`, catalogues fr/en/ar/tr —
     * #6874) avec repli technique non accentué quand le traducteur Laravel
     * n'est pas disponible (tests unitaires du contrat, PA2-I18N-007).
     *
     * @param  array<string, string>  $replace
     */
    private function message(string $key, array $replace, string $fallback): string
    {
        return ShowcaseMessage::get($key, $replace, $fallback);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  string  $pointer  Chemin JSON pointer concaténé (ex. content.items.2.title)
     * @param  array<string, list<string>>  $errors
     */
    private function checkNode(array $schema, mixed $value, string $pointer, array &$errors, bool $partial = false): void
    {
        $expected = $schema['type'] ?? null;

        if ($expected === 'object' || $expected === null) {
            if (! is_array($value)) {
                $errors[$pointer] = [sprintf('Le champ %s doit être un objet.', $pointer)];

                return;
            }

            // additionalProperties: false → clés inconnues refusées.
            if (($schema['additionalProperties'] ?? false) === false) {
                $allowed = array_keys($schema['properties'] ?? []);
                $unknown = array_diff(array_keys($value), $allowed);

                if ($unknown !== []) {
                    $errors[$pointer] = [sprintf(
                        'Clé(s) non autorisée(s) pour %s : %s.',
                        $pointer,
                        implode(', ', array_map(static fn (string $k): string => sprintf('« %s »', $k), $unknown))
                    )];

                    return;
                }
            }

            if (! $partial) {
                foreach (($schema['required'] ?? []) as $required) {
                    if (! array_key_exists($required, $value)) {
                        $errors[$pointer.'.'.$required] = [$this->message('section_field_required', ['field' => $required], 'The field '.$required.' is required.')];
                    }
                }
            }

            foreach ($value as $key => $child) {
                $childSchema = $schema['properties'][$key] ?? null;

                // Clé connue mais sans schéma détaillé → skippée (objet libre
                // interdit par additionalProperties: false ; défensif).
                if ($childSchema === null) {
                    continue;
                }

                $this->checkNode($childSchema, $child, $pointer.'.'.$key, $errors, $partial);
            }

            return;
        }

        if ($expected === 'array') {
            if (! is_array($value)) {
                $errors[$pointer] = [sprintf('Le champ %s doit être une liste.', $pointer)];

                return;
            }

            $count = count($value);

            if (! $partial && isset($schema['minItems']) && $count < $schema['minItems']) {
                $errors[$pointer] = [sprintf('Le champ %s doit contenir au moins %d élément(s).', $pointer, $schema['minItems'])];
            }

            if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
                $errors[$pointer] = [sprintf('Le champ %s ne doit pas dépasser %d éléments.', $pointer, $schema['maxItems'])];
            }

            $itemSchema = $schema['items'] ?? null;

            if ($itemSchema !== null) {
                foreach ($value as $index => $item) {
                    $this->checkNode($itemSchema, $item, $pointer.'.'.$index, $errors, $partial);
                }
            }

            return;
        }

        $this->checkScalar($schema, $value, $pointer, $errors);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, list<string>>  $errors
     */
    private function checkScalar(array $schema, mixed $value, string $pointer, array &$errors): void
    {
        $expected = $schema['type'] ?? 'string';

        $typeOk = match ($expected) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            default => false,
        };

        if (! $typeOk) {
            $errors[$pointer] = [sprintf('Le champ %s doit être de type %s.', $pointer, $expected)];

            return;
        }

        if ($expected === 'string' && is_string($value) && isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength']) {
            $errors[$pointer] = [sprintf('Le champ %s ne doit pas dépasser %d caractères.', $pointer, $schema['maxLength'])];
        }
    }
}
