<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExperienceTier\ExperienceTierBulkDestroyRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierDestroyRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierIndexRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierShowRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierStoreRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierUpdateRequest;
use App\Http\Resources\ExperienceTierResource;
use App\Models\ExperienceTier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExperienceTierController extends Controller
{
    public function index(ExperienceTierIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $experienceTiers = ExperienceTier::with($validated['with'] ?? [])->orderBy('min_match_count')->orderBy('id');

        if (isset($validated['search'])) {
            $experienceTiers->search($validated['search']);
        }

        if (isset($validated['tournament_id'])) {
            $experienceTiers->where('tournament_id', $validated['tournament_id']);
        }

        if ($validated['only_global'] ?? false) {
            $experienceTiers->whereNull('tournament_id');
        }

        if (isset($validated['enabled'])) {
            $experienceTiers->enabled($request->boolean('enabled'));
        }

        if ($validated['paginate'] ?? false) {
            $experienceTiers = $experienceTiers->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $experienceTiers = $experienceTiers->get();
        }

        return ExperienceTierResource::collection($experienceTiers);
    }

    public function store(ExperienceTierStoreRequest $request): ExperienceTierResource
    {
        $validated = $request->validated();

        $experienceTier = new ExperienceTier;
        $experienceTier->fill($validated)->saveOrFail();

        return new ExperienceTierResource($experienceTier->loadMissing($validated['with'] ?? []));
    }

    public function show(ExperienceTierShowRequest $request, ExperienceTier $experienceTier): ExperienceTierResource
    {
        $validated = $request->validated();

        return new ExperienceTierResource($experienceTier->loadMissing($validated['with'] ?? []));
    }

    public function update(ExperienceTierUpdateRequest $request, ExperienceTier $experienceTier): ExperienceTierResource
    {
        $validated = $request->validated();

        $experienceTier->fill($validated)->saveOrFail();

        return new ExperienceTierResource($experienceTier->loadMissing($validated['with'] ?? []));
    }

    public function destroy(ExperienceTierDestroyRequest $request, ExperienceTier $experienceTier): JsonResponse
    {
        $experienceTier->delete();

        return response()->json([], 204);
    }

    public function bulkDestroy(ExperienceTierBulkDestroyRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            ExperienceTier::whereIn('id', $request->validated('ids'))
                ->each(static fn (ExperienceTier $experienceTier) => $experienceTier->deleteOrFail());
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response()->json([], 204);
    }
}
