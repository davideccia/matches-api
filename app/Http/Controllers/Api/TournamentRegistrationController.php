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

        $registrations = $tournament->registrations()->with($validated['with'] ?? []);

        if (isset($validated['search'])) {
            $registrations->search($validated['search']);
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
