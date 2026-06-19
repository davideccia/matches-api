<?php

namespace App\Enums;

enum MatchRecordStatusEnum: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::SCHEDULED => __('enums.match_record_status.scheduled'),
            self::IN_PROGRESS => __('enums.match_record_status.in_progress'),
            self::COMPLETED => __('enums.match_record_status.completed'),
            self::CANCELLED => __('enums.match_record_status.cancelled'),
        };
    }
}
