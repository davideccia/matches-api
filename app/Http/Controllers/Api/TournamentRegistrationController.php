<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegistrationIndexRequest;
use App\Http\Requests\Registration\RegistrationStoreRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TournamentRegistrationController extends Controller
{
    public function index(RegistrationIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        $registrations = $tournament->registrations()->with($validated['with'] ?? [])->orderByDesc('registrations.created_at')->orderByDesc('registrations.id');

        if (isset($validated['search'])) {
            $registrations->search($validated['search']);
        }

        if (isset($validated['athlete_id'])) {
            $registrations->where('registrations.athlete_id', $validated['athlete_id']);
        }

        if (isset($validated['athlete_ids'])) {
            $registrations->whereIn('registrations.athlete_id', $validated['athlete_ids']);
        }

        if (isset($validated['discipline_id'])) {
            $registrations->where('registrations.discipline_id', $validated['discipline_id']);
        }

        if (isset($validated['discipline_ids'])) {
            $registrations->whereIn('registrations.discipline_id', $validated['discipline_ids']);
        }

        if (isset($validated['weight_category_id'])) {
            $registrations->where('registrations.weight_category_id', $validated['weight_category_id']);
        }

        if (isset($validated['weight_category_ids'])) {
            $registrations->whereIn('registrations.weight_category_id', $validated['weight_category_ids']);
        }

        if (isset($validated['unpaid'])) {
            $registrations->unpaid($validated['unpaid']);
        }

        if (isset($validated['unarrived'])) {
            $registrations->unarrived($validated['unarrived']);
        }

        if (isset($validated['weight_in_exceeded'])) {
            $registrations->weightInExceeded($validated['weight_in_exceeded']);
        }

        if (isset($validated['is_adult'])) {
            $registrations->athleteAdult($validated['is_adult']);
        }

        if ($validated['paginate'] ?? false) {
            $registrations = $registrations->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $registrations = $registrations->get();
        }

        return RegistrationResource::collection($registrations);
    }

    public function store(RegistrationStoreRequest $request, Tournament $tournament): RegistrationResource
    {
        $validated = $request->validated();

        $registration = new Registration;
        $registration->fill($validated)->saveOrFail();

        return new RegistrationResource($registration->loadMissing($validated['with'] ?? []));
    }
}
