<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchRecord\MatchRecordDestroyRequest;
use App\Http\Requests\MatchRecord\MatchRecordIndexRequest;
use App\Http\Requests\MatchRecord\MatchRecordShowRequest;
use App\Http\Requests\MatchRecord\MatchRecordStoreRequest;
use App\Http\Requests\MatchRecord\MatchRecordUpdateRequest;
use App\Http\Resources\MatchRecordResource;
use App\Models\MatchRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MatchRecordController extends Controller
{
    public function index(MatchRecordIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $matchRecords = MatchRecord::with($validated['with'] ?? []);

        if (isset($validated['search'])) {
            $matchRecords->search($validated['search']);
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
}
