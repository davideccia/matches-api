<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Athlete\AthleteBulkDestroyRequest;
use App\Http\Requests\Athlete\AthleteDestroyRequest;
use App\Http\Requests\Athlete\AthleteIndexRequest;
use App\Http\Requests\Athlete\AthleteShowRequest;
use App\Http\Requests\Athlete\AthleteStoreRequest;
use App\Http\Requests\Athlete\AthleteUpdateRequest;
use App\Http\Resources\AthleteResource;
use App\Models\Athlete;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;
use Throwable;

class AthleteController extends Controller
{
    public function index(AthleteIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $athletes = Athlete::query()
            ->with($validated['with'] ?? [])
            ->orderBy('full_name')->orderBy('id');

        if (isset($validated['search'])) {
            $athletes->search($validated['search']);
        }

        if (isset($validated['is_adult'])) {
            $athletes->adult($validated['is_adult']);
        }

        if (isset($validated['min_match_records_count'])) {
            $athletes->minMatchRecordsCount($validated['min_match_records_count']);
        }

        if (isset($validated['max_match_records_count'])) {
            $athletes->maxMatchRecordsCount($validated['max_match_records_count']);
        }

        if (isset($validated['gender'])) {
            $athletes->where('gender', $validated['gender']);
        }

        if (isset($validated['tournament_id'])) {
            $athletes->inTournament($validated['tournament_id'], ($validated['discipline_id'] ?? null), ($validated['weight_category_id'] ?? null));
        }

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

        DB::beginTransaction();

        $athlete = new Athlete;
        $athlete->fill($validated);

        $athlete->saveOrFail();

        if (isset($validated['photo'])) {
            $athlete->addMediaFromTemporaryFile($validated['photo'], Athlete::PHOTO_MEDIA_COLLECTION_NAME);
        }

        DB::commit();

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

        DB::beginTransaction();

        $athlete->fill($validated);

        $athlete->saveOrFail();

        if (isset($validated['photo'])) {
            $athlete->addMediaFromTemporaryFile($validated['photo'], Athlete::PHOTO_MEDIA_COLLECTION_NAME);
        }

        DB::commit();

        return new AthleteResource($athlete->loadMissing($validated['with'] ?? []));
    }

    public function destroy(AthleteDestroyRequest $request, Athlete $athlete): JsonResponse
    {
        $athlete->delete();

        return response()->json([], 204);
    }

    public function bulkDestroy(AthleteBulkDestroyRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            Athlete::whereIn('id', $request->validated('ids'))
                ->each(static fn (Athlete $athlete) => $athlete->deleteOrFail());
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return response()->json([], 204);
    }
}
