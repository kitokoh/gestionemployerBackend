<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Application\Actions\CreateShowcaseAction;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\ShowcaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BC-27 SHOWCASE — gestion de la vitrine du tenant (US1 : création 1-clic).
 *
 * Une seule vitrine par tenant : `GET /showcase` la retourne (404 tant que
 * le responsable ne l'a pas créée), `POST /showcase` la crée en `draft`
 * (slug = slug du tenant, thème `default`) — idempotent : 200 si elle
 * existe déjà, 201 à la création.
 *
 * RBAC : routes groupées `api.manager:principal,rh` + Policy
 * CompanyShowcasePolicy (create/update réservés principal/rh du tenant).
 * Les verbes suivent la convention #4930. La publication/dépublication
 * arrive en V-PUBLISH #6871 ; l'édition des sections en V-SECTIONS-API
 * #6866 (ShowcaseSectionController).
 */
final class ShowcaseController extends Controller
{
    public function __construct(private readonly CreateShowcaseAction $createShowcase) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        /** @var CompanyShowcase|null $showcase */
        $showcase = CompanyShowcase::query()->first();

        if (! $showcase instanceof CompanyShowcase) {
            abort(404, 'Aucune vitrine pour ce tenant — créez-la via POST /showcase.');
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
}
