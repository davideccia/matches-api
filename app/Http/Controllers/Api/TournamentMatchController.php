<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchRecord\MatchRecordIndexRequest;
use App\Http\Requests\MatchRecord\MatchRecordStoreRequest;
use App\Http\Resources\MatchRecordResource;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TournamentMatchController extends Controller
{
    public function index(MatchRecordIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        $matchRecords = MatchRecord::with($validated['with'] ?? [])
            ->where('tournament_id', $tournament->id);

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

    public function store(MatchRecordStoreRequest $request, Tournament $tournament): MatchRecordResource
    {
        $validated = $request->validated();

        $matchRecord = new MatchRecord;
        $matchRecord->fill($validated)->saveOrFail();

        return new MatchRecordResource($matchRecord->loadMissing($validated['with'] ?? []));
    }
}
