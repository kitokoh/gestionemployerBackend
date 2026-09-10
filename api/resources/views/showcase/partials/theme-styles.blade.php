{{--
    Variables de présentation du thème (BC-27 SHOWCASE, #6868).

    Toutes les valeurs proviennent de `ShowcaseThemeRegistry` (palette du
    thème + surcouche tenant) : couleurs validées `#RRGGBB`, polices et
    arrondis en allowlist. Aucune donnée tenant brute n'atteint la feuille de
    style → aucune injection CSS possible (défense en profondeur en plus de
    l'échappement Blade).
--}}
<style>
    :root {
        --showcase-primary: {{ $variables['primary'] }};
        --showcase-accent: {{ $variables['accent'] }};
        --showcase-surface: {{ $variables['surface'] }};
        --showcase-on-primary: {{ $variables['on_primary'] }};
        --showcase-ink: {{ $variables['ink'] }};
        --showcase-muted: {{ $variables['muted'] }};
        --showcase-border: {{ $variables['border'] }};
        --showcase-radius: {{ $variables['radius'] }};
        --showcase-font: {{ $variables['font_family'] }};
    }
</style>
