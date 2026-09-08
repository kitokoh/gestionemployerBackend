<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

/**
 * Validateur du contrat de sections vitrine (BC-27 SHOWCASE, #6866).
 *
 * Valide un contenu de section contre le sous-ensemble JSON Schema draft-07
 * défini par ShowcaseSectionSchemas. Implémentation volontairement minimale
 * (pas de dépendance) et stricte :
 *
 *   - clés inconnues refusées (additionalProperties: false, récursif) ;
 *   - types stricts (string/integer/boolean/array/object) ;
 *   - required, minLength/maxLength, maxItems, format uri|email.
 *
 * @see \App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas
 */
final class ShowcaseSectionContentValidator
{
    /**
     * Valide $content contre le schéma du type.
     *
     * @param  array<string, mixed>  $content
     * @return list<string> messages d'erreur (vide = valide)
     */
    public function validate(string $type, array $content): array
    {
        $schema = ShowcaseSectionSchemas::all()[$type] ?? null;
        if (! is_array($schema)) {
            return ["Unsupported section type: {$type}."];
        }

        $errors = [];
        $this->checkValue($schema, $content, '$', $errors);

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkValue(array $schema, mixed $value, string $path, array &$errors): void
    {
        switch ($schema['type'] ?? null) {
            case 'object':
                $this->checkObject($schema, $value, $path, $errors);

                return;
            case 'array':
                $this->checkArray($schema, $value, $path, $errors);

                return;
            case 'string':
                $this->checkString($schema, $value, $path, $errors);

                return;
            case 'integer':
                if (! is_int($value)) {
                    $errors[] = "{$path}: expected integer.";
                }

                return;
            case 'boolean':
                if (! is_bool($value)) {
                    $errors[] = "{$path}: expected boolean.";
                }

                return;
            default:
                $errors[] = "{$path}: unsupported schema type.";
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkObject(array $schema, mixed $value, string $path, array &$errors): void
    {
        if (! is_array($value)) {
            $errors[] = "{$path}: expected object.";

            return;
        }

        // {} JSON → [] en PHP (json_decode assoc) : objet vide valide.
        if ($value === []) {
            return;
        }

        if (array_is_list($value)) {
            $errors[] = "{$path}: expected object.";

            return;
        }

        $properties = $schema['properties'] ?? null;
        if (! is_array($properties)) {
            $errors[] = "{$path}: schema without properties.";

            return;
        }

        $required = $schema['required'] ?? [];
        if (is_array($required)) {
            foreach ($required as $key) {
                if (is_string($key) && ! array_key_exists($key, $value)) {
                    $errors[] = "{$path}.{$key}: required property missing.";
                }
            }
        }

        foreach ($value as $key => $child) {
            if (! is_string($key)) {
                continue;
            }
            if (! array_key_exists($key, $properties)) {
                $errors[] = "{$path}.{$key}: unknown property.";

                continue;
            }
            $childSchema = $properties[$key];
            if (is_array($childSchema)) {
                /** @var array<string, mixed> $typedSchema */
                $typedSchema = $childSchema;
                $this->checkValue($typedSchema, $child, "{$path}.{$key}", $errors);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkArray(array $schema, mixed $value, string $path, array &$errors): void
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $errors[] = "{$path}: expected array.";

            return;
        }

        $maxItems = $schema['maxItems'] ?? null;
        if (is_int($maxItems) && count($value) > $maxItems) {
            $errors[] = "{$path}: too many items (max {$maxItems}).";
        }

        $items = $schema['items'] ?? null;
        if (! is_array($items)) {
            return;
        }

        /** @var array<string, mixed> $typedItems */
        $typedItems = $items;
        foreach ($value as $index => $child) {
            $this->checkValue($typedItems, $child, "{$path}[{$index}]", $errors);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $errors
     */
    private function checkString(array $schema, mixed $value, string $path, array &$errors): void
    {
        if (! is_string($value)) {
            $errors[] = "{$path}: expected string.";

            return;
        }

        $length = mb_strlen($value);

        $minLength = $schema['minLength'] ?? null;
        if (is_int($minLength) && $length < $minLength) {
            $errors[] = "{$path}: too short (min {$minLength}).";
        }

        $maxLength = $schema['maxLength'] ?? null;
        if (is_int($maxLength) && $length > $maxLength) {
            $errors[] = "{$path}: too long (max {$maxLength}).";
        }

        $enum = $schema['enum'] ?? null;
        if (is_array($enum) && ! in_array($value, $enum, true)) {
            $errors[] = "{$path}: value not allowed.";
        }

        if ($value === '') {
            return;
        }

        $format = $schema['format'] ?? null;
        if ($format === 'uri' && filter_var($value, FILTER_VALIDATE_URL) === false) {
            $errors[] = "{$path}: invalid URL.";
        } elseif ($format === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "{$path}: invalid email.";
        }
    }
}
