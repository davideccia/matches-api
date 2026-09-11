<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchRecord\MatchRecordIndexRequest;
use App\Http\Resources\MatchRecordResource;
use App\Models\Athlete;
use App\Models\MatchRecord;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AthleteMatchRecordController extends Controller
{
    public function index(MatchRecordIndexRequest $request, Athlete $athlete): ResourceCollection
    {
        $validated = $request->validated();

        $matchRecords = MatchRecord::query()
            ->forAthlete($athlete->id)
            ->with($validated['with'] ?? [])
            ->orderBy('sort');

        if (isset($validated['search'])) {
            $matchRecords->search($validated['search']);
        }

        if (isset($validated['tournament_id'])) {
            $matchRecords->where('tournament_id', $validated['tournament_id']);
        }

        if (isset($validated['status'])) {
            $matchRecords->where('status', $validated['status']);
        }

        if ($validated['paginate'] ?? false) {
            $matchRecords = $matchRecords->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $matchRecords = $matchRecords->get();
        }

        return MatchRecordResource::collection($matchRecords);
    }
}
