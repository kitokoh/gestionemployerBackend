<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\ReorderShowcaseSectionsRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\StoreShowcaseSectionRequest;
use App\Modules\Showcase\Interfaces\Api\V1\Requests\UpdateShowcaseSectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRUD des sections de la vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * Écritures réservées au responsable du tenant (principal/rh —
 * ShowcaseSectionPolicy) ; lectures ouvertes aux membres du tenant.
 * Isolation tenant systématique : toute section hors `company_id` de
 * l'acteur est introuvable (404). Si le tenant n'a pas encore de vitrine
 * (création 1-clic #6865 non déclenchée), la première section crée une
 * vitrine `draft` par défaut (slug dérivé du nom de société).
 */
class ShowcaseSectionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $sections = ShowcaseSection::query()
            ->where('company_id', $actor->company_id)
            ->ordered()
            ->get()
            ->map(fn (ShowcaseSection $s): array => $this->payload($s));

        return response()->json(['data' => $sections->values()]);
    }

    public function store(StoreShowcaseSectionRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $showcase = $this->ensureShowcase($actor);

        $maxPosition = ShowcaseSection::query()
            ->where('company_id', $actor->company_id)
            ->where('showcase_id', $showcase->id)
            ->max('position');
        $nextPosition = is_numeric($maxPosition) ? (int) $maxPosition + 1 : 0;

        /** @var string $type */
        $type = $request->input('type');
        /** @var array<string, mixed> $content */
        $content = $request->input('content');
        $section = ShowcaseSection::query()->create([
            'company_id' => $actor->company_id,
            'showcase_id' => $showcase->id,
            'type' => $type,
            'schema_version' => ShowcaseSectionSchemas::VERSION,
            'content' => $content,
            'position' => $request->integer('position', $nextPosition),
        ]);

        return response()->json(['data' => $this->payload($section)], 201);
    }

    public function show(Request $request, ShowcaseSection $section): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($section->company_id !== $actor->company_id) {
            abort(404);
        }

        return response()->json(['data' => $this->payload($section)]);
    }

    public function update(UpdateShowcaseSectionRequest $request, ShowcaseSection $section): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')
            || $section->company_id !== $actor->company_id) {
            abort(404);
        }

        if ($request->has('content')) {
            /** @var array<string, mixed> $content */
            $content = $request->input('content');
            $section->content = $content;
        }
        if ($request->has('schema_version')) {
            $section->schema_version = $request->integer('schema_version');
        }
        if ($request->has('position')) {
            $section->position = $request->integer('position');
        }
        $section->save();

        return response()->json(['data' => $this->payload($section)]);
    }

    public function destroy(Request $request, ShowcaseSection $section): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')
            || $section->company_id !== $actor->company_id) {
            abort(404);
        }

        DB::transaction(static function () use ($section): void {
            $position = $section->position;
            $showcaseId = $section->showcase_id;
            $section->delete();
            // Recompactage des positions (trous évités après suppression).
            ShowcaseSection::query()
                ->where('showcase_id', $showcaseId)
                ->where('position', '>', $position)
                ->decrement('position');
        });

        return response()->json([], 204);
    }

    public function reorder(ReorderShowcaseSectionsRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if (! $actor->hasManagerRole('principal', 'rh')) {
            abort(403);
        }

        $rows = $request->input('sections');
        if (! is_array($rows)) {
            abort(422);
        }

        /** @var list<array{id: int, position: int}> $orders */
        $orders = $rows;
        $ids = array_map(static fn (array $row): int => $row['id'], $orders);

        $ownedCount = ShowcaseSection::query()
            ->where('company_id', $actor->company_id)
            ->whereIn('id', $ids)
            ->count();

        // Les ids sont uniques (validés) : même cardinalité ⇒ tous au tenant.
        if ($ownedCount !== count($ids)) {
            abort(404);
        }

        DB::transaction(static function () use ($orders): void {
            $ids = array_map(static fn (array $row): int => $row['id'], $orders);
            // Phase 1 : déplacement hors des positions finales (contrainte
            // unique (showcase_id, position) — évite les collisions
            // transitoires pendant le re-tri).
            ShowcaseSection::query()
                ->whereIn('id', $ids)
                ->update(['position' => DB::raw('position + 1000000')]);
            // Phase 2 : application des positions finales.
            foreach ($orders as $order) {
                ShowcaseSection::query()
                    ->where('id', $order['id'])
                    ->update(['position' => $order['position']]);
            }
        });

        $sections = ShowcaseSection::query()
            ->where('company_id', $actor->company_id)
            ->ordered()
            ->get()
            ->map(fn (ShowcaseSection $s): array => $this->payload($s));

        return response()->json(['data' => $sections->values()]);
    }

    /**
     * Vitrine du tenant, créée en `draft` au premier besoin (création
     * 1-clic) si absente — slug dérivé du nom de société, unique global.
     */
    private function ensureShowcase(Employee $actor): CompanyShowcase
    {
        $existing = CompanyShowcase::query()
            ->where('company_id', $actor->company_id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $company = $actor->company;
        $companyName = is_string($company?->name) ? $company->name : '';
        $base = $companyName !== '' ? Str::slug($companyName) : 'entreprise';
        $slug = $base !== '' ? $base : 'entreprise';
        $slug = substr($slug, 0, 140).'-'.strtolower(Str::random(6));

        return CompanyShowcase::query()->create([
            'company_id' => $actor->company_id,
            'slug' => $slug,
            'status' => CompanyShowcaseStatus::Draft,
            'theme' => 'default',
            'settings' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ShowcaseSection $section): array
    {
        return [
            'id' => $section->id,
            'type' => $section->type,
            'schema_version' => $section->schema_version,
            'position' => $section->position,
            'content' => $section->content,
            'created_at' => $section->created_at?->toIso8601String(),
            'updated_at' => $section->updated_at?->toIso8601String(),
        ];
    }
}
