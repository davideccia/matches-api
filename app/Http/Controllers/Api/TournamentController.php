<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\TournamentDestroyRequest;
use App\Http\Requests\Tournament\TournamentIndexRequest;
use App\Http\Requests\Tournament\TournamentShowRequest;
use App\Http\Requests\Tournament\TournamentStoreRequest;
use App\Http\Requests\Tournament\TournamentUpdateRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TournamentController extends Controller
{
    public function index(TournamentIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $tournaments = Tournament::with($validated['with'] ?? [])->orderByDesc('date')->orderByDesc('id');

        if (isset($validated['search'])) {
            $tournaments->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $tournaments = $tournaments->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $tournaments = $tournaments->get();
        }

        return TournamentResource::collection($tournaments);
    }

    public function store(TournamentStoreRequest $request): TournamentResource
    {
        $validated = $request->validated();

        $tournament = new Tournament;
        $tournament->fill($validated)->saveOrFail();

        return new TournamentResource($tournament->loadMissing($validated['with'] ?? []));
    }

    public function show(TournamentShowRequest $request, Tournament $tournament): TournamentResource
    {
        $validated = $request->validated();

        return new TournamentResource($tournament->loadMissing($validated['with'] ?? []));
    }

    public function update(TournamentUpdateRequest $request, Tournament $tournament): TournamentResource
    {
        $validated = $request->validated();

        $tournament->fill($validated)->saveOrFail();

        return new TournamentResource($tournament->loadMissing($validated['with'] ?? []));
    }

    public function destroy(TournamentDestroyRequest $request, Tournament $tournament): JsonResponse
    {
        $tournament->delete();

        return response()->json([], 204);
    }
}
