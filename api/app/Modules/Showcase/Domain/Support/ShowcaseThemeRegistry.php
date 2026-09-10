<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Support;

use App\Modules\Showcase\Domain\Enums\ShowcaseTheme;

/**
 * Registre des thèmes v1 de la vitrine (BC-27 SHOWCASE, #6868 V-THEMES).
 *
 * Un thème = variables de présentation (palette, typo, arrondi) + jeu de
 * templates de sections (résolus par `ShowcaseThemeRenderer`). Le CONTENU des
 * sections n'est jamais stocké dans un thème : le même contenu se re-rend sous
 * Industrie, Service et Commerce (spec §7).
 *
 * Toutes les valeurs par défaut sont issues des design tokens produit
 * (`docs/REFERENTIEL_PRODUIT/COULEURS.md`) :
 *   - `#10B981` (vert RH / succès), `#F59E0B` (finance / avertissement),
 *     `#3B82F6` (info / sécurité), `#06B6D4` (cyan 500), neutres slate
 *     (`#0F172A`, `#F8FAFC`, `#64748B`, `#E2E8F0`) ;
 *   - typo Inter (`docs/specifications/DESIGN_SYSTEM_TOKENS.md`), arrondi 8px.
 *
 * Les variables fournies par le tenant (couleurs, typo, arrondi) sont
 * appliquées en surcouche, avec repli propre sur le thème (allowlist stricte :
 * toute valeur invalide retombe sur la valeur du thème, jamais d'injection
 * CSS).
 */
final class ShowcaseThemeRegistry
{
    /** Thème de repli par défaut (v1). */
    public const DEFAULT = 'industrie';

    /**
     * Définition des thèmes v1.
     *
     * @var array<string, array<string, string>>
     */
    private const THEMES = [
        'industrie' => [
            'primary' => '#0F172A',
            'accent' => '#10B981',
            'surface' => '#F8FAFC',
            'on_primary' => '#FFFFFF',
            'ink' => '#0F172A',
            'muted' => '#64748B',
            'border' => '#E2E8F0',
            'font_family' => 'inter',
            'radius' => 'md',
        ],
        'service' => [
            'primary' => '#3B82F6',
            'accent' => '#06B6D4',
            'surface' => '#FFFFFF',
            'on_primary' => '#FFFFFF',
            'ink' => '#0F172A',
            'muted' => '#64748B',
            'border' => '#E2E8F0',
            'font_family' => 'inter',
            'radius' => 'lg',
        ],
        'commerce' => [
            'primary' => '#F59E0B',
            'accent' => '#10B981',
            'surface' => '#FFFBEB',
            'on_primary' => '#0F172A',
            'ink' => '#0F172A',
            'muted' => '#64748B',
            'border' => '#E2E8F0',
            'font_family' => 'inter',
            'radius' => 'md',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function v1(): array
    {
        return ShowcaseTheme::v1();
    }

    public static function isKnown(string $theme): bool
    {
        return array_key_exists($theme, self::THEMES);
    }

    /**
     * Définition brute d'un thème (repli = thème par défaut si inconnu).
     *
     * @return array<string, string>
     */
    public static function definition(string $theme): array
    {
        return self::THEMES[$theme] ?? self::THEMES[self::DEFAULT];
    }

    /**
     * Résout les variables de présentation effectives : thème + surcouche
     * tenant (allowlist), avec repli propre.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    public static function resolveVariables(string $theme, array $settings): array
    {
        $definition = self::definition($theme);

        $colors = is_array($settings['colors'] ?? null) ? $settings['colors'] : [];

        return [
            'primary' => self::color($colors['primary'] ?? null, $definition['primary']),
            'accent' => self::color($colors['accent'] ?? null, $definition['accent']),
            'surface' => self::color($colors['surface'] ?? null, $definition['surface']),
            'on_primary' => self::color($colors['on_primary'] ?? null, $definition['on_primary']),
            'ink' => $definition['ink'],
            'muted' => $definition['muted'],
            'border' => $definition['border'],
            'font_family' => self::fontFamily($settings['font_family'] ?? null, $definition['font_family']),
            'radius' => self::radius($settings['radius'] ?? null, $definition['radius']),
        ];
    }

    /**
     * @return list<string>
     */
    public static function allowedFonts(): array
    {
        return ['inter', 'system', 'serif', 'mono'];
    }

    /**
     * @return list<string>
     */
    public static function allowedRadii(): array
    {
        return ['none', 'sm', 'md', 'lg', 'full'];
    }

    /**
     * Couleur hexadécimale validée (`#RRGGBB`), sinon valeur du thème.
     */
    private static function color(mixed $value, string $default): string
    {
        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
            return strtoupper($value);
        }

        return $default;
    }

    /**
     * Pile de polices : identifiant d'allowlist → stack CSS, sinon thème.
     */
    private static function fontFamily(mixed $value, string $default): string
    {
        $key = is_string($value) ? strtolower($value) : '';
        $key = in_array($key, self::allowedFonts(), true) ? $key : $default;

        return match ($key) {
            'inter' => 'Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
            'system' => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
            'serif' => 'Georgia, Times New Roman, serif',
            'mono' => 'JetBrains Mono, ui-monospace, SFMono-Regular, Menlo, monospace',
            default => 'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
        };
    }

    /**
     * Rayon : identifiant d'allowlist → valeur CSS, sinon thème.
     */
    private static function radius(mixed $value, string $default): string
    {
        $key = is_string($value) ? strtolower($value) : '';
        $key = in_array($key, self::allowedRadii(), true) ? $key : $default;

        return match ($key) {
            'none' => '0',
            'sm' => '0.25rem',
            'md' => '0.5rem',
            'lg' => '1rem',
            'full' => '999px',
            default => '0.5rem',
        };
    }
}
