<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTournament\PublicTournamentIndexRequest;
use App\Http\Requests\PublicTournament\PublicTournamentMatchRecordIndexRequest;
use App\Http\Resources\MatchRecordResource;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicTournamentController extends Controller
{
    public function tournamentsIndex(PublicTournamentIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $tournaments = Tournament::with($validated['with'] ?? [])
            ->whereIn('status', [TournamentStatus::IN_PROGRESS, TournamentStatus::COMPLETED])
            ->orderByDesc('date')
            ->orderByDesc('id');

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

    public function tournamentMatchRecords(PublicTournamentMatchRecordIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        abort_unless(
            in_array($tournament->status->value, [TournamentStatus::IN_PROGRESS->value, TournamentStatus::COMPLETED->value], true),
            404
        );

        $validated = $request->validated();

        $matchRecords = $tournament->matchRecords()->with($validated['with'] ?? []);

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
}
