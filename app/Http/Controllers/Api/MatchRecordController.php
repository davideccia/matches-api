<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchRecord\MatchRecordBulkDestroyRequest;
use App\Http\Requests\MatchRecord\MatchRecordDestroyRequest;
use App\Http\Requests\MatchRecord\MatchRecordIndexRequest;
use App\Http\Requests\MatchRecord\MatchRecordShowRequest;
use App\Http\Requests\MatchRecord\MatchRecordStoreRequest;
use App\Http\Requests\MatchRecord\MatchRecordUpdateRequest;
use App\Http\Resources\MatchRecordResource;
use App\Models\MatchRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class MatchRecordController extends Controller
{
    public function index(MatchRecordIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $matchRecords = MatchRecord::with($validated['with'] ?? [])->orderBy('sort');

        if (isset($validated['search'])) {
            $matchRecords->search($validated['search']);
        }

        if (isset($validated['tournament_id'])) {
            $matchRecords->where('tournament_id', $validated['tournament_id']);
        }

        if ($validated['paginate'] ?? false) {
            $matchRecords = $matchRecords->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $matchRecords = $matchRecords->get();
        }

        return MatchRecordResource::collection($matchRecords);
    }

    public function store(MatchRecordStoreRequest $request): MatchRecordResource
    {
        $validated = $request->validated();

        $matchRecord = new MatchRecord;
        $matchRecord->fill($validated)->saveOrFail();

        return new MatchRecordResource($matchRecord->loadMissing($validated['with'] ?? []));
    }

    public function show(MatchRecordShowRequest $request, MatchRecord $matchRecord): MatchRecordResource
    {
        $validated = $request->validated();

        return new MatchRecordResource($matchRecord->loadMissing($validated['with'] ?? []));
    }

    public function update(MatchRecordUpdateRequest $request, MatchRecord $matchRecord): MatchRecordResource
    {
        $validated = $request->validated();

        $matchRecord->fill($validated)->saveOrFail();

        return new MatchRecordResource($matchRecord->loadMissing($validated['with'] ?? []));
    }

    public function destroy(MatchRecordDestroyRequest $request, MatchRecord $matchRecord): JsonResponse
    {
        $matchRecord->delete();

        return response()->json([], 204);
    }

    public function bulkDestroy(MatchRecordBulkDestroyRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            MatchRecord::whereIn('id', $request->validated('ids'))->get()
                ->each(fn (MatchRecord $matchRecord) => $matchRecord->delete());
        });

        return response()->json([], 204);
    }
}
