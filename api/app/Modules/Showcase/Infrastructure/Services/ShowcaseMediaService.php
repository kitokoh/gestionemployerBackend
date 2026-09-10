<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Infrastructure\Services;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Upload / suppression des médias d'une vitrine (BC-27 SHOWCASE, #6872
 * V-MEDIA).
 *
 * S'appuie sur le service de stockage existant (`Storage`, disque `public` —
 * `config/filesystems.php`, pattern CabinetService #1817 et
 * CompanyBrandingController). Le nom d'origine n'est JAMAIS utilisé comme
 * chemin : le fichier est stocké sous `showcase/{company_id}/{showcase_id}/
 * {uuid}.{ext}` (uuid = identifiant public stable) et `original_name` est
 * sanitisé (basename + caractères de contrôle retirés) pour l'affichage seul.
 *
 * Le média est relié à la vitrine (logo → `settings.logo_id`) ou à une section
 * (`section_id`) par `uuid` — jamais par chemin absolu côté client. Toute
 * mutation invalide le cache public si la vitrine est publiée.
 */
final class ShowcaseMediaService
{
    /** Disque de stockage des médias vitrine (cf. config/filesystems.php). */
    public const DISK = 'public';

    public function __construct(
        private readonly ShowcaseImageOptimizer $optimizer,
        private readonly ShowcasePublicCache $cache,
    ) {}

    public function upload(
        CompanyShowcase $showcase,
        UploadedFile $file,
        string $kind,
        ?int $sectionId = null,
        ?int $actorId = null,
    ): ShowcaseMedia {
        $originalName = $this->sanitizeName($file->getClientOriginalName());
        $extension = strtolower($file->getClientOriginalExtension());
        $contents = (string) $file->get();

        $processed = $this->optimizer->process($contents, $extension);

        $uuid = (string) Str::uuid();
        $path = sprintf(
            'showcase/%s/%d/%s.%s',
            $showcase->company_id,
            $showcase->id,
            $uuid,
            $processed['extension']
        );

        Storage::disk(self::DISK)->put($path, $processed['contents']);

        $dimensions = $this->dimensions($processed['contents']);

        /** @var ShowcaseMedia $media */
        $media = ShowcaseMedia::query()->create([
            'company_id' => $showcase->company_id,
            'showcase_id' => $showcase->id,
            'section_id' => $sectionId,
            'uuid' => $uuid,
            'kind' => $kind,
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $processed['mime_type'],
            'size' => strlen($processed['contents']),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'checksum' => hash('sha256', $processed['contents']),
        ]);

        if (ShowcaseMedia::isLogoKind($kind)) {
            $this->attachLogo($showcase, $media->uuid);
        }

        $this->cache->forget($showcase->slug);

        AuditLog::create([
            'company_id' => $showcase->company_id,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.media_uploaded',
            'auditable_type' => ShowcaseMedia::class,
            'auditable_id' => $media->id,
            'old_values' => [],
            'new_values' => ['kind' => $kind, 'uuid' => $media->uuid],
        ]);

        return $media;
    }

    public function delete(ShowcaseMedia $media, ?int $actorId = null): void
    {
        Storage::disk($media->disk)->delete($media->path);

        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->find($media->showcase_id);

        if ($showcase instanceof CompanyShowcase && ($showcase->settings['logo_id'] ?? null) === $media->uuid) {
            $settings = $showcase->settings ?? [];
            unset($settings['logo_id']);
            $showcase->settings = $settings;
            $showcase->save();
        }

        $companyId = $media->company_id;

        $media->delete();

        if ($showcase instanceof CompanyShowcase) {
            $this->cache->forget($showcase->slug);
        }

        AuditLog::create([
            'company_id' => $companyId,
            'user_id' => $actorId,
            'module' => 'showcase',
            'action' => 'showcase.media_deleted',
            'auditable_type' => ShowcaseMedia::class,
            'auditable_id' => $media->id,
            'old_values' => ['uuid' => $media->uuid],
            'new_values' => [],
        ]);
    }

    /**
     * @return list<ShowcaseMedia>
     */
    public function list(CompanyShowcase $showcase): array
    {
        /** @var list<ShowcaseMedia> $media */
        $media = ShowcaseMedia::query()
            ->where('showcase_id', $showcase->id)
            ->orderBy('id')
            ->get()
            ->all();

        return $media;
    }

    private function attachLogo(CompanyShowcase $showcase, string $uuid): void
    {
        $settings = $showcase->settings ?? [];
        $settings['logo_id'] = $uuid;
        $showcase->settings = $settings;
        $showcase->save();
    }

    /**
     * Nom d'affichage sûr : basename, sans séparateur de chemin ni caractère
     * de contrôle (le chemin de stockage réel est dérivé de l'uuid).
     */
    private function sanitizeName(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $base) ?? '';
        $clean = trim($clean);

        if ($clean === '') {
            return 'media';
        }

        return mb_substr($clean, 0, 200);
    }

    /**
     * @return array{width: int|null, height: int|null}
     */
    private function dimensions(string $contents): array
    {
        $size = @getimagesizefromstring($contents);

        if (! is_array($size)) {
            return ['width' => null, 'height' => null];
        }

        /** @var int|float $width */
        $width = $size[0];
        /** @var int|float $height */
        $height = $size[1];

        return ['width' => (int) $width, 'height' => (int) $height];
    }
}
