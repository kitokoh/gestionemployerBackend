<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Application\Actions\CreateShowcaseAction;
use App\Modules\Showcase\Application\Actions\PublishShowcaseAction;
use App\Modules\Showcase\Application\Actions\RotateShowcasePreviewTokenAction;
use App\Modules\Showcase\Application\Actions\UnpublishShowcaseAction;
use App\Modules\Showcase\Application\Actions\UpdateShowcaseSettingsAction;
use App\Modules\Showcase\Application\Actions\UpdateShowcaseThemeAction;
use App\Modules\Showcase\Domain\Enums\ShowcaseTheme;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * BC-27 SHOWCASE — gestion de la vitrine du tenant.
 *
 * Une seule vitrine par tenant : `GET /showcase` la retourne (404 tant que le
 * responsable ne l'a pas créée), `POST /showcase` la crée en `draft` (slug du
 * tenant, thème `default`) — idempotent (200 si elle existe déjà, 201 sinon).
 *
 * V-PUBLISH #6871 : `POST /showcase/publish`, `POST /showcase/unpublish`
 * (workflow `draft ↔ published`) et `POST /showcase/preview-token` (jeton
 * d'aperçu privé d'un brouillon).
 * V-RGPD #6875 : `PATCH /showcase/settings` (variables de marque + bloc
 * mentions légales / politique de confidentialité éditables).
 * V-THEMES #6868 : `PATCH /showcase` étend le PATCH aux réglages/légal et
 * sélectionne le thème v1 (Industrie/Service/Commerce, allowlist).
 *
 * RBAC : routes groupées `api.manager:principal,rh` + Policy
 * CompanyShowcasePolicy (update/publish réservés principal/rh du tenant) ;
 * isolation tenant par le scope global `company_id` (fail-closed #3727).
 * Les verbes suivent la convention #4930.
 */
final class ShowcaseController extends Controller
{
    public function __construct(
        private readonly CreateShowcaseAction $createShowcase,
        private readonly PublishShowcaseAction $publishShowcase,
        private readonly UnpublishShowcaseAction $unpublishShowcase,
        private readonly RotateShowcasePreviewTokenAction $rotatePreviewToken,
        private readonly UpdateShowcaseSettingsAction $updateSettings,
        private readonly UpdateShowcaseThemeAction $updateTheme,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->first();

        if (! $showcase instanceof CompanyShowcase) {
            abort(404);
        }

        if ($actor->cannot('view', $showcase)) {
            abort(403);
        }

        return (new ShowcaseResource($showcase))->response();
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', CompanyShowcase::class)) {
            abort(403);
        }

        $created = ! CompanyShowcase::query()->exists();

        $showcase = $this->createShowcase->execute(currentCompany());

        return (new ShowcaseResource($showcase))
            ->response()
            ->setStatusCode($created ? JsonResponse::HTTP_CREATED : JsonResponse::HTTP_OK);
    }

    public function publish(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        $this->publishShowcase->execute($showcase, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    public function unpublish(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        $this->unpublishShowcase->execute($showcase, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    public function previewToken(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        $token = $this->rotatePreviewToken->execute($showcase, $actor->id);

        return response()->json([
            'data' => [
                'preview_token' => $token,
                'preview_path' => '/public/vitrine/'.$showcase->slug.'?token='.$token,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        $payload = $request->validate([
            'theme' => ['sometimes', 'string', Rule::in(ShowcaseTheme::v1())],
            'settings' => ['sometimes', 'array'],
            'legal' => ['sometimes', 'array'],
        ]);

        if (array_key_exists('theme', $payload)) {
            $this->updateTheme->execute($showcase, (string) $payload['theme'], $actor->id);
        }

        if (array_key_exists('settings', $payload) || array_key_exists('legal', $payload)) {
            $this->updateSettings->execute($showcase, $payload, $actor->id);
        }

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        $payload = $request->validate([
            'settings' => ['sometimes', 'array'],
            'legal' => ['sometimes', 'array'],
        ]);

        /** @var array<string, mixed> $validated */
        $validated = $payload;

        $this->updateSettings->execute($showcase, $validated, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    private function currentShowcaseOrFail(): CompanyShowcase
    {
        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->first();

        if (! $showcase instanceof CompanyShowcase) {
            abort(404);
        }

        return $showcase;
    }

    private function authorizeUpdate(Request $request, CompanyShowcase $showcase): Employee
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $showcase)) {
            abort(403);
        }

        return $actor;
    }
}
