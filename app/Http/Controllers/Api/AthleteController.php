<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Athlete\AthleteDestroyRequest;
use App\Http\Requests\Athlete\AthleteIndexRequest;
use App\Http\Requests\Athlete\AthleteShowRequest;
use App\Http\Requests\Athlete\AthleteStoreRequest;
use App\Http\Requests\Athlete\AthleteUpdateRequest;
use App\Http\Resources\AthleteResource;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AthleteController extends Controller
{
    public function index(AthleteIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $athletes = Athlete::with($validated['with'] ?? []);

        if ($validated['paginate'] ?? false) {
            $athletes = $athletes->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $athletes = $athletes->get();
        }

        return AthleteResource::collection($athletes);
    }

    public function store(AthleteStoreRequest $request): AthleteResource
    {
        $validated = $request->validated();

        $athlete = new Athlete;
        $athlete->fill($validated)->saveOrFail();

        return new AthleteResource($athlete->loadMissing($validated['with'] ?? []));
    }

    public function show(AthleteShowRequest $request, Athlete $athlete): AthleteResource
    {
        $validated = $request->validated();

        return new AthleteResource($athlete->loadMissing($validated['with'] ?? []));
    }

    public function update(AthleteUpdateRequest $request, Athlete $athlete): AthleteResource
    {
        $validated = $request->validated();

        $athlete->fill($validated)->saveOrFail();

        return new AthleteResource($athlete->loadMissing($validated['with'] ?? []));
    }

    public function destroy(AthleteDestroyRequest $request, Athlete $athlete): JsonResponse
    {
        $athlete->delete();

        return response()->json([], 204);
    }
}
