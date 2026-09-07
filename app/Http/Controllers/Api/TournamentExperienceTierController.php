<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExperienceTier\ExperienceTierBatchRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierIndexRequest;
use App\Http\Requests\ExperienceTier\ExperienceTierStoreRequest;
use App\Http\Resources\ExperienceTierResource;
use App\Models\ExperienceTier;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class TournamentExperienceTierController extends Controller
{
    public function index(ExperienceTierIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        $experienceTiers = $tournament->experienceTiers()->with($validated['with'] ?? []);

        if (isset($validated['search'])) {
            $experienceTiers->search($validated['search']);
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

    public function store(ExperienceTierStoreRequest $request, Tournament $tournament): ExperienceTierResource
    {
        $validated = $request->validated();

        $experienceTier = new ExperienceTier;
        $experienceTier->fill($validated)->saveOrFail();

        return new ExperienceTierResource($experienceTier->loadMissing($validated['with'] ?? []));
    }

    public function batch(ExperienceTierBatchRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $experienceTiers = collect($validated['experience_tiers'])
                ->map(function (array $attributes): ExperienceTier {
                    $experienceTier = new ExperienceTier;
                    $experienceTier->fill($attributes)->saveOrFail();

                    return $experienceTier;
                });
        } catch (Throwable $e) {
            DB::rollBack();

            abort(400, $e->getMessage());
        }

        DB::commit();

        return ExperienceTierResource::collection(
            $experienceTiers->each->loadMissing($validated['with'] ?? [])
        );
    }
}
