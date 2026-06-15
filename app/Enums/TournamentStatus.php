<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case SCHEDULED = 'scheduled';
    case REGISTRATIONS_OPENED = 'registrations_opened';
    case REGISTRATIONS_CLOSED = 'registrations_closed';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
