<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicTournament\PublicTournamentCurrentMatchRecordsRequest;
use App\Http\Requests\PublicTournament\PublicTournamentIndexRequest;
use App\Http\Requests\PublicTournament\PublicTournamentMatchRecordIndexRequest;
use App\Http\Resources\Public\PublicMatchRecordResource;
use App\Http\Resources\Public\PublicTournamentResource;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicTournamentController extends Controller
{
    public function tournamentsIndex(PublicTournamentIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $tournaments = Tournament::with($validated['with'] ?? [])
            ->where('status', TournamentStatusEnum::IN_PROGRESS)
            ->orderByDesc('date')
            ->orderBy('name')
            ->orderByDesc('id');

        if (isset($validated['search'])) {
            $tournaments->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $tournaments = $tournaments->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $tournaments = $tournaments->get();
        }

        return PublicTournamentResource::collection($tournaments);
    }

    public function tournamentMatchRecords(PublicTournamentMatchRecordIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        abort_unless(
            in_array($tournament->status->value, [TournamentStatusEnum::IN_PROGRESS->value, TournamentStatusEnum::COMPLETED->value], true),
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

        return PublicMatchRecordResource::collection($matchRecords);
    }

    public function currentMatchRecords(PublicTournamentCurrentMatchRecordsRequest $request, Tournament $tournament): JsonResponse
    {
        abort_unless(
            in_array($tournament->status->value, [TournamentStatusEnum::IN_PROGRESS->value, TournamentStatusEnum::COMPLETED->value], true),
            404
        );

        $validated = $request->validated();

        $window = $tournament->getCurrentMatchRecordsWindow();

        foreach ($window as $matchRecord) {
            $matchRecord?->loadMissing($validated['with'] ?? []);
        }

        return response()->json([
            'previous' => $window['previous'] ? new PublicMatchRecordResource($window['previous']) : null,
            'current' => $window['current'] ? new PublicMatchRecordResource($window['current']) : null,
            'next' => $window['next'] ? new PublicMatchRecordResource($window['next']) : null,
        ]);
    }
}
