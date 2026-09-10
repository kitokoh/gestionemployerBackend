<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BC-27 SHOWCASE (#6872 V-MEDIA) — service public d'un média de vitrine.
 *
 * `GET /public/vitrine/{slug}/media/{media}` sert le fichier (logo / image de
 * section) d'une vitrine PUBLIÉE, avec des cache headers longs et immuables
 * (`Cache-Control: public, max-age=31536000, immutable`) — le nom de fichier
 * (`uuid` stable) ne change jamais, un remplacement crée un nouveau média.
 *
 * Fail-closed : slug inconnu, société suspendue, vitrine absente / non publiée
 * (sauf jeton d'aperçu `?token=` #6871) ou média hors vitrine → 404. Le média
 * est résolu DANS le tenant (TenantManager::withinTenant + scope company_id) :
 * jamais de fichier d'un autre tenant. Les SVG (logo) sont servis sous CSP
 * restrictive (`sandbox`, `default-src 'none'`).
 */
final class ShowcasePublicMediaController extends Controller
{
    public const NOINDEX_HEADER = 'noindex, nofollow';

    public function __construct(private readonly TenantManager $tenantManager) {}

    public function show(Request $request, string $slug, string $uuid): Response
    {
        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('status', '!=', 'suspended')
            ->first();

        if (! $company instanceof Company) {
            return $this->notFound();
        }

        /** @var array{disk: string, path: string, name: string, mime: string}|null $payload */
        $payload = $this->tenantManager->withinTenant($company, function () use ($request, $slug, $uuid): ?array {
            /** @var CompanyShowcase|null $showcase */
            $showcase = CompanyShowcase::query()->where('slug', $slug)->first();

            if (! $showcase instanceof CompanyShowcase) {
                return null;
            }

            if (! $this->isAccessible($request, $showcase)) {
                return null;
            }

            /** @var ShowcaseMedia|null $media */
            $media = ShowcaseMedia::query()
                ->where('showcase_id', $showcase->id)
                ->where('uuid', $uuid)
                ->first();

            if (! $media instanceof ShowcaseMedia) {
                return null;
            }

            return [
                'disk' => $media->disk,
                'path' => $media->path,
                'name' => $media->original_name,
                'mime' => $media->mime_type,
            ];
        });

        if ($payload === null) {
            return $this->notFound();
        }

        $headers = [
            'Content-Type' => $payload['mime'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ];

        // SVG = vecteur actif : jamais exécutable dans le contexte de la page.
        if ($payload['mime'] === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        if (! Storage::disk($payload['disk'])->exists($payload['path'])) {
            return $this->notFound();
        }

        /** @var StreamedResponse $response */
        $response = Storage::disk($payload['disk'])->response($payload['path'], $payload['name'], $headers);

        return $response;
    }

    /**
     * Vitrine publiée, ou brouillon explicitement prévisualisé avec le jeton
     * privé (même règle que la page/API publique).
     */
    private function isAccessible(Request $request, CompanyShowcase $showcase): bool
    {
        if ($showcase->status === CompanyShowcaseStatus::Published) {
            return true;
        }

        $token = $request->query('token');

        return is_string($token)
            && $token !== ''
            && is_string($showcase->preview_token)
            && $showcase->preview_token !== ''
            && hash_equals($showcase->preview_token, $token);
    }

    private function notFound(): JsonResponse
    {
        return response()
            ->json(['message' => 'Not Found'], Response::HTTP_NOT_FOUND)
            ->header('X-Robots-Tag', self::NOINDEX_HEADER);
    }
}
