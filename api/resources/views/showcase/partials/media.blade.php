{{--
    Visuel de section (BC-27 SHOWCASE, #6872).

    Résolution serveur : un `image_id` (uuid d'un média vitrine exposé dans
    `$mediaMap`) prime sur `image_url` (URL externe de repli). Aucune valeur
    n'est concaténée dans un chemin : l'URL est construite par le DTO public.
    Les variables sont optionnelles : `mediaId`, `mediaUrl`, `alt`, `class`.
--}}
@php
    $mediaId = is_string($mediaId ?? null) ? $mediaId : null;
    $mediaUrl = is_string($mediaUrl ?? null) ? $mediaUrl : null;
    $altText = is_string($alt ?? null) ? $alt : '';
    $mediaClass = is_string($class ?? null) ? $class : null;

    $resolvedMedia = $mediaId !== null && is_string($mediaMap[$mediaId]['url'] ?? null)
        ? $mediaMap[$mediaId]['url']
        : $mediaUrl;
@endphp
@if (is_string($resolvedMedia) && trim($resolvedMedia) !== '')
    <img src="{{ $resolvedMedia }}" alt="{{ $altText }}" loading="lazy" decoding="async"@if ($mediaClass !== null) class="{{ $mediaClass }}"@endif>
@endif
