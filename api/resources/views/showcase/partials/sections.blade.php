{{--
    Boucle de rendu des sections (BC-27 SHOWCASE, #6868).

    Le CONTENU (`$vitrine['sections']`) est séparé de la PRÉSENTATION : chaque
    type est rendu par la vue résolue par `ShowcaseThemeRenderer`
    (`$sectionViews[type]`, avec repli sur le thème v1 par défaut). Un type
    inconnu — ou non couvert par un template — est ignoré, jamais d'erreur.
--}}
@foreach (($vitrine['sections'] ?? []) as $section)
    @php
        $sectionType = is_array($section) && is_string($section['type'] ?? null) ? $section['type'] : null;
        $sectionContent = is_array($section['content'] ?? null) ? $section['content'] : [];
        $sectionView = $sectionType !== null ? ($sectionViews[$sectionType] ?? null) : null;
    @endphp
    @if (is_string($sectionView) && $sectionView !== '')
        @include($sectionView, ['content' => $sectionContent])
    @endif
@endforeach
