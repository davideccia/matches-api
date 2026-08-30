<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tournament\TournamentBulkDestroyRequest;
use App\Http\Requests\Tournament\TournamentDestroyRequest;
use App\Http\Requests\Tournament\TournamentIndexRequest;
use App\Http\Requests\Tournament\TournamentShowRequest;
use App\Http\Requests\Tournament\TournamentStoreRequest;
use App\Http\Requests\Tournament\TournamentUpdateRequest;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

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

        \DB::beginTransaction();

        $tournament = (new Tournament)->fill($validated);
        $tournament->saveOrFail();

        if (isset($validated['cover'])) {
            $tournament->addMediaFromTemporaryFile($validated['cover'], Tournament::COVER_MEDIA_COLLECTION_NAME);
        }

        // An absent key leaves the pivot untouched; an empty array detaches every discipline.
        if (array_key_exists('disciplines', $validated)) {
            $tournament->disciplines()->sync($validated['disciplines'] ?? []);
        }

        \DB::commit();

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

        \DB::beginTransaction();

        $tournament->fill($validated);
        $tournament->saveOrFail();

        if (isset($validated['cover'])) {
            $tournament->addMediaFromTemporaryFile($validated['cover'], Tournament::COVER_MEDIA_COLLECTION_NAME);
        }

        // An absent key leaves the pivot untouched; an empty array detaches every discipline.
        if (array_key_exists('disciplines', $validated)) {
            $tournament->disciplines()->sync($validated['disciplines'] ?? []);
        }

        \DB::commit();

        return new TournamentResource($tournament->loadMissing($validated['with'] ?? []));
    }

    public function destroy(TournamentDestroyRequest $request, Tournament $tournament): JsonResponse
    {
        $tournament->deleteOrFail();

        return response()->json([], 204);
    }

    public function bulkDestroy(TournamentBulkDestroyRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            Tournament::whereIn('id', $request->validated('ids'))
                ->each(static fn (Tournament $tournament) => $tournament->deleteOrFail());
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response()->json([], 204);
    }
}
