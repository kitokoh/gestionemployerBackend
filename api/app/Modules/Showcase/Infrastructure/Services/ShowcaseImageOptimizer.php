<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

/**
 * Optimisation d'image à l'upload vitrine (BC-27 SHOWCASE, #6872 V-MEDIA).
 *
 * « Redimensionnement/variants si outillage présent, sinon limite de poids +
 * WebP à l'upload » (issue #6872). L'outillage est GD/WebP : s'il est
 * disponible, l'image raster est bornée (`MAX_DIMENSION`) et ré-encodée en
 * WebP ; sinon (ou pour un SVG) le fichier est conservé tel quel — la limite
 * de poids reste garantie par la validation FormRequest.
 *
 * Aucune dépendance ajoutée : seules les fonctions GD optionnelles sont
 * appelées, derrière {@see canEncodeWebp()}.
 */
final class ShowcaseImageOptimizer
{
    /** Dimension maximale (px) conservée à l'upload. */
    public const MAX_DIMENSION = 2400;

    private const WEBP_QUALITY = 82;

    /**
     * @return array{contents: string, extension: string, mime_type: string}
     */
    public function process(string $contents, string $extension): array
    {
        $extension = strtolower($extension);

        if ($extension === 'svg' || ! $this->canEncodeWebp()) {
            return [
                'contents' => $contents,
                'extension' => $extension,
                'mime_type' => $this->mimeFor($extension),
            ];
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof \GdImage) {
            return [
                'contents' => $contents,
                'extension' => $extension,
                'mime_type' => $this->mimeFor($extension),
            ];
        }

        $encoded = $this->encodeWebp($image);

        if ($encoded === null) {
            return [
                'contents' => $contents,
                'extension' => $extension,
                'mime_type' => $this->mimeFor($extension),
            ];
        }

        return [
            'contents' => $encoded,
            'extension' => 'webp',
            'mime_type' => 'image/webp',
        ];
    }

    public function canEncodeWebp(): bool
    {
        return function_exists('imagecreatefromstring')
            && function_exists('imagewebp')
            && function_exists('imagecreatetruecolor');
    }

    /**
     * @return string|null Bytes WebP, ou null si l'encodage échoue.
     */
    private function encodeWebp(\GdImage $image): ?string
    {
        $resized = $this->downscale($image);

        ob_start();
        $ok = imagewebp($resized, null, self::WEBP_QUALITY);
        $bytes = ob_get_clean();

        if ($resized !== $image) {
            imagedestroy($resized);
        }

        imagedestroy($image);

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    /**
     * Réduit l'image si elle dépasse {@see MAX_DIMENSION} (ratio conservé).
     * Retourne la même ressource (non détruite) si aucune réduction n'est
     * nécessaire.
     */
    private function downscale(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largest = max($width, $height);

        if ($largest <= self::MAX_DIMENSION) {
            return $image;
        }

        $ratio = self::MAX_DIMENSION / $largest;
        $targetWidth = max(1, (int) floor($width * $ratio));
        $targetHeight = max(1, (int) floor($height * $ratio));

        $target = @imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $target instanceof \GdImage) {
            return $image;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $target;
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
