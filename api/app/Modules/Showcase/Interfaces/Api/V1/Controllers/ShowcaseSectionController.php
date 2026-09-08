<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Application\Actions\CreateShowcaseSectionAction;
use App\Modules\Showcase\Application\Actions\DeleteShowcaseSectionAction;
use App\Modules\Showcase\Application\Actions\ListShowcaseSectionsAction;
use App\Modules\Showcase\Application\Actions\ReorderShowcaseSectionsAction;
use App\Modules\Showcase\Application\Actions\UpdateShowcaseSectionAction;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\ReorderShowcaseSectionsRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\StoreShowcaseSectionRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\UpdateShowcaseSectionRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseSectionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-27 SHOWCASE (#6866) — API privée CRUD des sections de la vitrine du
 * tenant (verbes convention #4930).
 *
 * - `GET/POST /showcase/sections`, `PATCH/DELETE /showcase/sections/{id}`,
 *   réordonnancement `POST /showcase/sections/reorder` (ids ordonnés) ;
 * - chaque `content` est validé par type contre le JSON Schema versionné
 *   (ShowcaseSectionSchemaValidator → 422) ;
 * - RBAC `api.manager:principal,rh` + CompanyShowcasePolicy (update) ;
 * - isolation tenant : scope BelongsToCompany (fail-closed #3727) — un id
 *   d'une autre société est un 404, jamais un 403 sur la ressource ;
 * - cache public invalidé à chaque mutation d'une vitrine publiée.
 */
final class ShowcaseSectionController extends Controller
{
    public function __construct(
        private readonly ListShowcaseSectionsAction $listSections,
        private readonly CreateShowcaseSectionAction $createSection,
        private readonly UpdateShowcaseSectionAction $updateSection,
        private readonly DeleteShowcaseSectionAction $deleteSection,
        private readonly ReorderShowcaseSectionsAction $reorderSections,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail($request);

        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $showcase)) {
            abort(403);
        }

        return ShowcaseSectionResource::collection($this->listSections->execute($showcase))->response();
    }

    public function store(StoreShowcaseSectionRequest $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail($request);
        $this->authorizeUpdate($request, $showcase);

        /** @var string $type */
        $type = $request->validated('type');

        /** @var array<string, mixed> $content */
        $content = $request->validated('content');

        $section = $this->createSection->execute($showcase, $type, $content);

        return (new ShowcaseSectionResource($section))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function update(UpdateShowcaseSectionRequest $request, CompanyShowcaseSection $section): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail($request);

        // L'id d'une section d'une autre vitrine du même tenant (ou d'un
        // autre tenant — scope global) ne doit jamais être adressable.
        if ($section->showcase_id !== $showcase->id) {
            abort(404);
        }

        $this->authorizeUpdate($request, $showcase);

        $type = $request->validated('type');
        $content = $request->validated('content');

        $updated = $this->updateSection->execute(
            $showcase,
            $section,
            is_string($type) ? $type : null,
            is_array($content) ? $content : null,
        );

        return (new ShowcaseSectionResource($updated))->response();
    }

    public function destroy(Request $request, CompanyShowcaseSection $section): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail($request);

        if ($section->showcase_id !== $showcase->id) {
            abort(404);
        }

        $this->authorizeUpdate($request, $showcase);

        $this->deleteSection->execute($showcase, $section);

        return response()->noContent();
    }

    /**
     * Réordonnancement complet : `ids` = ordre cible (liste complète des
     * sections existantes). Retourne la liste rafraîchie.
     */
    public function reorder(ReorderShowcaseSectionsRequest $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail($request);
        $this->authorizeUpdate($request, $showcase);

        $this->reorderSections->execute($showcase, array_map('intval', (array) $request->validated('ids')));

        return ShowcaseSectionResource::collection($this->listSections->execute($showcase))->response();
    }

    private function currentShowcaseOrFail(Request $request): CompanyShowcase
    {
        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->first();

        if (! $showcase instanceof CompanyShowcase) {
            abort(404, 'Aucune vitrine pour ce tenant — créez-la via POST /showcase.');
        }

        return $showcase;
    }

    private function authorizeUpdate(Request $request, CompanyShowcase $showcase): void
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $showcase)) {
            abort(403);
        }
    }
}
