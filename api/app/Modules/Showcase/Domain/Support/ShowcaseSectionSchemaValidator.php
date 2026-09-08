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
     * @param  array<string, mixed>  $content
     * @return array<string, mixed> Contenu normalisé (clés connues uniquement).
     *
     * @throws ValidationException
     */
    public function validateOrFail(string $type, array $content): array
    {
        $errors = $this->validate($type, $content);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, list<string>> Erreurs indexées par chemin (style Laravel).
     */
    public function validate(string $type, array $content): array
    {
        $schema = ShowcaseSectionSchemaRegistry::schemaFor($type);

        if ($schema === null) {
            return ['type' => [sprintf('Type de section inconnu : « %s ».', $type)]];
        }

        $errors = [];
        $this->checkNode($schema, $content, 'content', $errors);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $path  Segments du chemin (ex. content, items, 2, title)
     * @param  array<string, list<string>>  $errors
     */
    private function checkNode(array $schema, mixed $value, string $pointer, array &$errors): void
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

            foreach (($schema['required'] ?? []) as $required) {
                if (! array_key_exists($required, $value)) {
                    $errors[$pointer.'.'.$required] = [sprintf('Le champ %s est requis.', $required)];
                }
            }

            foreach ($value as $key => $child) {
                $childSchema = $schema['properties'][$key] ?? null;

                // Clé connue mais sans schéma détaillé → skippée (objet libre
                // interdit par additionalProperties: false ; défensif).
                if ($childSchema === null) {
                    continue;
                }

                $this->checkNode($childSchema, $child, $pointer.'.'.$key, $errors);
            }

            return;
        }

        if ($expected === 'array') {
            if (! is_array($value)) {
                $errors[$pointer] = [sprintf('Le champ %s doit être une liste.', $pointer)];

                return;
            }

            $count = count($value);

            if (isset($schema['minItems']) && $count < $schema['minItems']) {
                $errors[$pointer] = [sprintf('Le champ %s doit contenir au moins %d élément(s).', $pointer, $schema['minItems'])];
            }

            if (isset($schema['maxItems']) && $count > $schema['maxItems']) {
                $errors[$pointer] = [sprintf('Le champ %s ne doit pas dépasser %d éléments.', $pointer, $schema['maxItems'])];
            }

            $itemSchema = $schema['items'] ?? null;

            if ($itemSchema !== null) {
                foreach ($value as $index => $item) {
                    $this->checkNode($itemSchema, $item, $pointer.'.'.$index, $errors);
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
