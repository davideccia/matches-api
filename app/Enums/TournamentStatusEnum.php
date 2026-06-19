<?php

namespace App\Enums;

enum TournamentStatusEnum: string
{
    case SCHEDULED = 'scheduled';
    case REGISTRATIONS_OPENED = 'registrations_opened';
    case REGISTRATIONS_CLOSED = 'registrations_closed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => __('enums.tournament_status.scheduled'),
            self::REGISTRATIONS_OPENED => __('enums.tournament_status.registrations_opened'),
            self::REGISTRATIONS_CLOSED => __('enums.tournament_status.registrations_closed'),
            self::IN_PROGRESS => __('enums.tournament_status.in_progress'),
            self::COMPLETED => __('enums.tournament_status.completed'),
            self::CANCELLED => __('enums.tournament_status.cancelled'),
        };
    }
}
