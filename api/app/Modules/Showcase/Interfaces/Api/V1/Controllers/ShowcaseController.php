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
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-27 SHOWCASE — gestion de la vitrine du tenant.
 *
 * Une seule vitrine par tenant : `GET /showcase` la retourne (404 tant que
 * le responsable ne l'a pas créée), `POST /showcase` la crée en `draft`.
 *
 * V-PUBLISH #6871 : `POST /showcase/publish`, `POST /showcase/unpublish`,
 * `POST /showcase/preview-token` (jeton d'aperçu privé) ;
 * V-THEMES #6868 / V-RGPD #6875 : `PUT /showcase/settings` (thème, variables
 * de marque, bloc légal).
 *
 * RBAC : routes groupées `api.manager:principal,rh` + Policy
 * CompanyShowcasePolicy (update réservé principal/rh du tenant). Les verbes
 * suivent la convention #4930.
 */
final class ShowcaseController extends Controller
{
    public function __construct(
        private readonly CreateShowcaseAction $createShowcase,
        private readonly PublishShowcaseAction $publishShowcase,
        private readonly UnpublishShowcaseAction $unpublishShowcase,
        private readonly RotateShowcasePreviewTokenAction $rotatePreviewToken,
        private readonly UpdateShowcaseSettingsAction $updateSettings,
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
        $showcase = $this->showcaseOr404();
        $actor = $this->authorizeUpdate($request, $showcase);

        $this->publishShowcase->execute($showcase, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    public function unpublish(Request $request): JsonResponse
    {
        $showcase = $this->showcaseOr404();
        $actor = $this->authorizeUpdate($request, $showcase);

        $this->unpublishShowcase->execute($showcase, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    public function previewToken(Request $request): JsonResponse
    {
        $showcase = $this->showcaseOr404();
        $actor = $this->authorizeUpdate($request, $showcase);

        $token = $this->rotatePreviewToken->execute($showcase, $actor->id);

        return response()->json([
            'data' => [
                'preview_token' => $token,
                'preview_path' => '/public/vitrine/'.$showcase->slug.'?token='.$token,
                'expires' => null,
            ],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $showcase = $this->showcaseOr404();
        $actor = $this->authorizeUpdate($request, $showcase);

        $payload = $request->validate([
            'theme' => ['sometimes', 'string', 'max:60'],
            'settings' => ['sometimes', 'array'],
            'legal' => ['sometimes', 'array'],
        ]);

        $this->updateSettings->execute($showcase, $payload, $actor->id);

        return (new ShowcaseResource($showcase->refresh()))->response();
    }

    private function showcaseOr404(): CompanyShowcase
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
