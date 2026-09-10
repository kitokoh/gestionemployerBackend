<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseLocaleResolver;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\VitrinePublicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;

/**
 * BC-27 SHOWCASE — API publique d'une vitrine (V-PUBLIC-API #6867, étendue
 * V-PUBLISH #6871, V-SEO #6873).
 *
 * Route isolée SANS auth (groupe `throttle:shop-public`), zéro donnée
 * interne : le tenant est résolu par le slug (`companies.slug` en schéma
 * public), puis lecture dans le schéma tenant via
 * TenantManager::withinTenant avec le scope company_id. Le DTO public
 * (VitrinePublicResource) est le SEUL contrat exposé — jamais les modèles
 * Eloquent bruts.
 *
 * - `GET /public/vitrine/{slug}` : vitrine publiée ; un brouillon n'est servi
 *   qu'avec le jeton d'aperçu privé `?token=` (#6871), alors avec
 *   `X-Robots-Tag: noindex, nofollow` et jamais mis en cache ;
 * - 404 (brouillon / slug inconnu / société suspendue) porte
 *   `X-Robots-Tag: noindex, nofollow` — jamais indexable (#6873) ;
 * - `GET /public/vitrine/sitemap.xml` : vitrines PUBLIÉES uniquement ;
 * - `GET /public/vitrine/robots.txt` : indexation des vitrines, aperçus
 *   exclus, pointeur sitemap.
 */
final class ShowcasePublicController extends Controller
{
    public const NOINDEX_HEADER = 'noindex, nofollow';

    private const SITEMAP_CACHE_KEY = 'showcase:public:sitemap:v1';

    private const SITEMAP_MAX_COMPANIES = 500;

    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ShowcasePublicCache $cache,
        private readonly ShowcaseLocaleResolver $localeResolver,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $providedToken = $request->query('token');
        $providedToken = is_string($providedToken) && $providedToken !== '' ? $providedToken : null;

        $isPreview = false;

        // #6874 — locale de contenu (`?lang=` puis `Accept-Language`, repli fr).
        $locale = $this->localeResolver->resolve($request);

        /** @var array<string, mixed>|null $payload */
        $payload = $this->resolvePayload($request, $slug, $locale, $providedToken, $isPreview);

        if ($payload === null) {
            return response()
                ->json(['message' => 'Not Found'], HttpResponse::HTTP_NOT_FOUND)
                ->header('X-Robots-Tag', self::NOINDEX_HEADER);
        }

        $response = response()->json(['data' => $payload]);

        if ($isPreview) {
            $response->header('X-Robots-Tag', self::NOINDEX_HEADER);
        }

        return $response;
    }

    /**
     * V-SEO #6873 — sitemap des vitrines PUBLIÉES (jamais de brouillon).
     *
     * Liste cross-tenant bornée (`SITEMAP_MAX_COMPANIES`) et mise en cache
     * court (TTL du cache public) : le sitemap ne contient que des URL
     * canoniques `/vitrine/{slug}` des vitrines réellement publiées.
     */
    public function sitemap(): HttpResponse
    {
        /** @var string $xml */
        $xml = Cache::remember(
            self::SITEMAP_CACHE_KEY,
            now()->addSeconds(ShowcasePublicCache::TTL_SECONDS),
            fn (): string => $this->buildSitemap()
        );

        return response($xml, HttpResponse::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * V-SEO #6873 — robots.txt public : indexation des vitrines autorisée,
     * aperçus (`?token=`) et API exclus, pointeur sitemap.
     */
    public function robots(): HttpResponse
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /vitrine/',
            'Disallow: /vitrine/*?token=',
            'Disallow: /api/',
            'Sitemap: '.url('/vitrine/sitemap.xml'),
            '',
        ]);

        return response($body, HttpResponse::HTTP_OK, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /**
     * @param  string|null  $providedToken
     */
    private function resolvePayload(Request $request, string $slug, string $locale, ?string $providedToken, bool &$isPreview): ?array
    {
        $resolver = function () use ($request, $slug, $locale, $providedToken, &$isPreview): ?array {
            /** @var Company|null $company */
            $company = Company::query()
                ->where('slug', $slug)
                ->where('status', '!=', 'suspended')
                ->first();

            if (! $company instanceof Company) {
                return null;
            }

            return $this->tenantManager->withinTenant($company, function () use ($request, $slug, $locale, $providedToken, $company, &$isPreview): ?array {
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

                $resource = new VitrinePublicResource($showcase, $sections, $company->name, $media, $locale);

                return $resource->resolve($request);
            });
        };

        if ($providedToken !== null) {
            // Aperçu : jamais servi depuis le cache (sinon un brouillon
            // empoisonnerait la clé publique du slug).
            return $resolver();
        }

        // #6874 — clé de cache par locale : jamais le contenu d'une locale
        // servi sous une autre.
        return $this->cache->remember($slug, $locale, $resolver);
    }

    private function buildSitemap(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        /** @var list<Company> $companies */
        $companies = Company::query()
            ->where('status', '!=', 'suspended')
            ->limit(self::SITEMAP_MAX_COMPANIES)
            ->get()
            ->all();

        foreach ($companies as $company) {
            $this->tenantManager->withinTenant($company, function () use ($company, &$lines): void {
                /** @var CompanyShowcase|null $showcase */
                $showcase = CompanyShowcase::query()
                    ->where('company_id', $company->id)
                    ->where('status', CompanyShowcaseStatus::Published)
                    ->first();

                if (! $showcase instanceof CompanyShowcase) {
                    return;
                }

                $lastmod = ($showcase->published_at ?? $showcase->updated_at)?->toAtomString();
                $loc = htmlspecialchars(url('/vitrine/'.$showcase->slug), ENT_XML1 | ENT_QUOTES);

                $lines[] = '  <url>';
                $lines[] = '    <loc>'.$loc.'</loc>';
                if ($lastmod !== null) {
                    $lines[] = '    <lastmod>'.$lastmod.'</lastmod>';
                }
                $lines[] = '  </url>';
            });
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    }
}
