<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Web\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseThemeRenderer;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\VitrinePublicResource;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * BC-27 SHOWCASE — rendu SSR de la vitrine publique (V-SEO #6873).
 *
 * `GET /vitrine/{slug}` : page HTML complète rendue côté serveur (aucun JS
 * requis), avec `<title>`, meta description, Open Graph et canonical — donc
 * indexable sans prerender. La page consomme EXACTEMENT le même DTO public
 * que l'API (`VitrinePublicResource`) : aucune divergence API/HTML et zéro
 * donnée interne.
 *
 * Fail-closed : slug inconnu, société suspendue, vitrine absente ou non
 * publiée (sans jeton d'aperçu) → 404 avec `X-Robots-Tag: noindex, nofollow`
 * (une page brouillon/inexistante n'est JAMAIS indexée). Un brouillon peut
 * être prévisualisé avec `?token=` (récupéré via `POST /showcase/preview-token`),
 * alors également servi en `noindex`.
 *
 * Aucun cookie tiers n'est déposé : la bannière cookies est purement
 * informative et mémorise la préférence côté client (`localStorage`).
 */
final class ShowcasePublicPageController extends Controller
{
    public const NOINDEX_HEADER = 'noindex, nofollow';

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ShowcasePublicCache $cache,
        private readonly ShowcaseThemeRenderer $renderer,
    ) {}

    public function show(Request $request, string $slug): Response
    {
        $providedToken = $request->query('token');
        $providedToken = is_string($providedToken) && $providedToken !== '' ? $providedToken : null;

        $isPreview = false;

        /** @var array<string, mixed>|null $payload */
        $payload = $this->resolvePayload($request, $slug, $providedToken, $isPreview);

        if ($payload === null) {
            return response()
                ->view('showcase.not-found', ['title' => (string) __('showcase.not_found_title')], Response::HTTP_NOT_FOUND)
                ->header('X-Robots-Tag', self::NOINDEX_HEADER);
        }

        $theme = is_string($payload['theme'] ?? null) ? $payload['theme'] : ShowcaseThemeRegistry::DEFAULT;
        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        $companyName = is_string($payload['company_name'] ?? null) ? $payload['company_name'] : '';

        $response = response()->view($this->renderer->pageView($theme), [
            'vitrine' => $payload,
            'theme' => $theme,
            'variables' => $this->renderer->variables($theme, $settings, $companyName),
            'sectionViews' => $this->renderer->sectionViews($theme, $this->sectionTypes($payload)),
            'mediaMap' => $this->mediaMap($payload),
            'contactAction' => url('/api/v1/public/vitrine/'.$slug.'/contact'),
        ]);

        if ($isPreview) {
            $response->header('X-Robots-Tag', self::NOINDEX_HEADER);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function sectionTypes(array $payload): array
    {
        $sections = $payload['sections'] ?? null;

        if (! is_array($sections)) {
            return [];
        }

        $types = [];

        foreach ($sections as $section) {
            if (is_array($section) && is_string($section['type'] ?? null)) {
                $types[] = $section['type'];
            }
        }

        return $types;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function mediaMap(array $payload): array
    {
        $media = $payload['media'] ?? null;

        if (! is_array($media)) {
            return [];
        }

        $map = [];

        foreach ($media as $item) {
            if (is_array($item) && is_string($item['id'] ?? null)) {
                $map[$item['id']] = $item;
            }
        }

        return $map;
    }

    /**
     * @param  string|null  $providedToken
     */
    private function resolvePayload(Request $request, string $slug, ?string $providedToken, bool &$isPreview): ?array
    {
        $resolver = function () use ($request, $slug, $providedToken, &$isPreview): ?array {
            /** @var Company|null $company */
            $company = Company::query()
                ->where('slug', $slug)
                ->where('status', '!=', 'suspended')
                ->first();

            if (! $company instanceof Company) {
                return null;
            }

            return $this->tenantManager->withinTenant($company, function () use ($request, $slug, $providedToken, $company, &$isPreview): ?array {
                /** @var CompanyShowcase|null $showcase */
                $showcase = CompanyShowcase::query()
                    ->where('slug', $slug)
                    ->first();

                if (! $showcase instanceof CompanyShowcase) {
                    return null;
                }

                $isPublished = $showcase->status === CompanyShowcaseStatus::Published;

                $previewAuthorized = ! $isPublished
                    && $providedToken !== null
                    && is_string($showcase->preview_token)
                    && $showcase->preview_token !== ''
                    && hash_equals($showcase->preview_token, $providedToken);

                if (! $isPublished && ! $previewAuthorized) {
                    return null;
                }

                $isPreview = $previewAuthorized;

                /** @var list<CompanyShowcaseSection> $sections */
                $sections = CompanyShowcaseSection::query()
                    ->where('showcase_id', $showcase->id)
                    ->ordered()
                    ->get()
                    ->all();

                /** @var list<ShowcaseMedia> $media */
                $media = ShowcaseMedia::query()
                    ->where('showcase_id', $showcase->id)
                    ->orderBy('id')
                    ->get()
                    ->all();

                return (new VitrinePublicResource($showcase, $sections, $company->name, $media))->resolve($request);
            });
        };

        if ($providedToken !== null) {
            return $resolver();
        }

        return $this->cache->remember($slug, $resolver);
    }
}
