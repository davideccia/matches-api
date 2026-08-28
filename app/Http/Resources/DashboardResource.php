<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tournament_id' => $this->id,
            'tournament_name' => $this->name,
            'status' => $this->status->value,
            'date_from' => $this->date_from->toDateString(),
            'date_to' => $this->date_to->toDateString(),
            'total_registrations' => (int) $this->totalRegistrations,
            'arrived_registrations' => (int) $this->arrivedRegistrations,
            'absent_registrations' => (int) $this->totalRegistrations - (int) $this->arrivedRegistrations,
            'paid_registrations' => (int) $this->paidRegistrations,
            'unpaid_registrations' => (int) $this->totalRegistrations - (int) $this->paidRegistrations,
            'total_matches' => (int) $this->totalMatches,
            'scheduled_matches' => (int) $this->scheduledMatches,
            'in_progress_matches' => (int) $this->inProgressMatches,
            'completed_matches' => (int) $this->completedMatches,
            'cancelled_matches' => (int) $this->cancelledMatches,
        ];
    }
}
