<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationPdfRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormShowRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormTournamentIndexRequest;
use App\Http\Resources\AthleteResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\TournamentResource;
use App\Models\Athlete;
use App\Models\Registration;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PublicRegistrationFormController extends Controller
{
    public function showAthlete(PublicRegistrationFormShowRequest $request, Athlete $athlete): AthleteResource
    {
        return new AthleteResource($athlete);
    }

    public function storeAthlete(PublicRegistrationFormStoreRequest $request): AthleteResource
    {
        $validated = $request->validated();

        $athlete = Athlete::updateOrCreate(
            ['tax_number' => $validated['tax_number']],
            $validated,
        );

        return new AthleteResource($athlete);
    }

    public function tournamentsIndex(PublicRegistrationFormTournamentIndexRequest $request): ResourceCollection
    {
        $tournaments = Tournament::where('status', TournamentStatusEnum::REGISTRATIONS_OPENED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return TournamentResource::collection($tournaments);
    }

    public function storeRegistration(PublicRegistrationFormRegistrationStoreRequest $request): RegistrationResource
    {
        $validated = $request->validated();

        $registration = new Registration;
        $registration->fill($validated)->saveOrFail();

        return new RegistrationResource($registration);
    }

    public function registrationPdf(PublicRegistrationFormRegistrationPdfRequest $request, Registration $registration): JsonResponse
    {
        abort(404);
    }
}
