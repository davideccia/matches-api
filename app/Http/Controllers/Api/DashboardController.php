<?php

namespace App\Http\Controllers\Api;

use App\Enums\MatchRecordStatusEnum;
use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class DashboardController extends Controller
{
    public function index(): ResourceCollection
    {
        $tournaments = Tournament::whereIn('status', [
            TournamentStatusEnum::REGISTRATIONS_OPENED,
            TournamentStatusEnum::REGISTRATIONS_CLOSED,
            TournamentStatusEnum::IN_PROGRESS,
        ])
            ->withCount([
                'registrations as totalRegistrations',
                'registrations as arrivedRegistrations' => fn ($q) => $q->where('arrived', true),
                'registrations as paidRegistrations' => fn ($q) => $q->whereNotNull('paid_at'),
                'matchRecords as totalMatches',
                'matchRecords as scheduledMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::SCHEDULED),
                'matchRecords as inProgressMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::IN_PROGRESS),
                'matchRecords as completedMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::COMPLETED),
                'matchRecords as cancelledMatches' => fn ($q) => $q->where('status', MatchRecordStatusEnum::CANCELLED),
            ])
            ->orderByDesc('date_from')
            ->orderBy('id')
            ->get();

        return DashboardResource::collection($tournaments);
    }
}
