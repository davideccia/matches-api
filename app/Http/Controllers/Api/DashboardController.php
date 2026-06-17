<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchStatus;
use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DashboardController extends Controller
{
    public function index(): ResourceCollection
    {
        $tournaments = Tournament::whereNotIn('status', [
            TournamentStatus::COMPLETED,
            TournamentStatus::CANCELLED,
        ])
            ->withCount([
                'registrations as totalRegistrations',
                'registrations as arrivedRegistrations' => fn ($q) => $q->where('arrived', true),
                'registrations as paidRegistrations' => fn ($q) => $q->whereNotNull('paid_at'),
                'matchRecords as totalMatches',
                'matchRecords as scheduledMatches' => fn ($q) => $q->where('status', MatchStatus::SCHEDULED),
                'matchRecords as inProgressMatches' => fn ($q) => $q->where('status', MatchStatus::IN_PROGRESS),
                'matchRecords as completedMatches' => fn ($q) => $q->where('status', MatchStatus::COMPLETED),
                'matchRecords as cancelledMatches' => fn ($q) => $q->where('status', MatchStatus::CANCELLED),
            ])
            ->orderByDesc('date')
            ->orderBy('id')
            ->get();

        return DashboardResource::collection($tournaments);
    }
}
