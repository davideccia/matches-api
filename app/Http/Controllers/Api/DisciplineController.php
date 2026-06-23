<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discipline\DisciplineBulkDestroyRequest;
use App\Http\Requests\Discipline\DisciplineDestroyRequest;
use App\Http\Requests\Discipline\DisciplineIndexRequest;
use App\Http\Requests\Discipline\DisciplineShowRequest;
use App\Http\Requests\Discipline\DisciplineStoreRequest;
use App\Http\Requests\Discipline\DisciplineUpdateRequest;
use App\Http\Resources\DisciplineResource;
use App\Models\Discipline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class DisciplineController extends Controller
{
    public function index(DisciplineIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $disciplines = Discipline::with($validated['with'] ?? [])->orderBy('label')->orderBy('id');

        if (isset($validated['search'])) {
            $disciplines->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $disciplines = $disciplines->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $disciplines = $disciplines->get();
        }

        return DisciplineResource::collection($disciplines);
    }

    public function store(DisciplineStoreRequest $request): DisciplineResource
    {
        $validated = $request->validated();

        $discipline = new Discipline;
        $discipline->fill($validated)->saveOrFail();

        return new DisciplineResource($discipline->loadMissing($validated['with'] ?? []));
    }

    public function show(DisciplineShowRequest $request, Discipline $discipline): DisciplineResource
    {
        $validated = $request->validated();

        return new DisciplineResource($discipline->loadMissing($validated['with'] ?? []));
    }

    public function update(DisciplineUpdateRequest $request, Discipline $discipline): DisciplineResource
    {
        $validated = $request->validated();

        $discipline->fill($validated)->saveOrFail();

        return new DisciplineResource($discipline->loadMissing($validated['with'] ?? []));
    }

    public function destroy(DisciplineDestroyRequest $request, Discipline $discipline): JsonResponse
    {
        $discipline->delete();

        return response()->json([], 204);
    }

    public function bulkDestroy(DisciplineBulkDestroyRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            Discipline::whereIn('id', $request->validated('ids'))->get()
                ->each(fn (Discipline $discipline) => $discipline->delete());
        });

        return response()->json([], 204);
    }
}
