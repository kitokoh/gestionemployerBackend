<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseMediaService;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\StoreShowcaseMediaRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseMediaResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6872 V-MEDIA) — gestion privée des médias de la vitrine.
 *
 * - `GET /showcase/media` : liste des médias de la vitrine (scope tenant) ;
 * - `POST /showcase/media` : upload (logo ou image de section) — validation
 *   type/taille (StoreShowcaseMediaRequest), stockage via le service existant,
 *   nom sanitisé, référence `uuid` stable ; un logo alimente automatiquement
 *   `settings.logo_id` ;
 * - `DELETE /showcase/media/{media}` : suppression (fichier + ligne).
 *
 * RBAC `api.manager:principal,rh` + CompanyShowcasePolicy (update) ; isolation
 * tenant : le binding `{media}` passe par le scope `company_id` (fail-closed
 * #3727) — un média d'un autre tenant est un 404.
 */
final class ShowcaseMediaController extends Controller
{
    public function __construct(private readonly ShowcaseMediaService $mediaService) {}

    public function index(Request $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();

        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $showcase)) {
            abort(403);
        }

        return ShowcaseMediaResource::collection($this->mediaService->list($showcase))->response();
    }

    public function store(StoreShowcaseMediaRequest $request): JsonResponse
    {
        $showcase = $this->currentShowcaseOrFail();
        $actor = $this->authorizeUpdate($request, $showcase);

        /** @var string $kind */
        $kind = $request->validated('kind');

        $sectionId = $this->sectionId($request, $showcase);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $media = $this->mediaService->upload($showcase, $file, $kind, $sectionId, $actor->id);

        return (new ShowcaseMediaResource($media))->response()->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(Request $request, ShowcaseMedia $media): Response
    {
        $showcase = $this->currentShowcaseOrFail();

        if ($media->showcase_id !== $showcase->id) {
            abort(404);
        }

        $actor = $this->authorizeUpdate($request, $showcase);

        $this->mediaService->delete($media, $actor->id);

        return response()->noContent();
    }

    /**
     * Valide que la section ciblée appartient bien à la vitrine courante
     * (aucun rattachement d'un média à une section d'un autre tenant/vitrine).
     */
    private function sectionId(StoreShowcaseMediaRequest $request, CompanyShowcase $showcase): ?int
    {
        $sectionId = $request->validated('section_id');

        if ($sectionId === null) {
            return null;
        }

        $id = (int) $sectionId;

        $belongs = CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->whereKey($id)
            ->exists();

        if (! $belongs) {
            throw ValidationException::withMessages([
                'section_id' => [(string) __('showcase.media_section_not_found')],
            ]);
        }

        return $id;
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
