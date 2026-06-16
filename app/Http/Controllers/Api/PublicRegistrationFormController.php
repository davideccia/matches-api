<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormDisciplineIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationPdfRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormShowRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormTournamentIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormWeightCategoryIndexRequest;
use App\Http\Resources\AthleteResource;
use App\Http\Resources\DisciplineResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\TournamentResource;
use App\Http\Resources\WeightCategoryResource;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
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
        $tournaments = Tournament::where('status', TournamentStatus::REGISTRATIONS_OPENED)
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        return TournamentResource::collection($tournaments);
    }

    public function disciplinesIndex(PublicRegistrationFormDisciplineIndexRequest $request): ResourceCollection
    {
        return DisciplineResource::collection(Discipline::orderBy('label')->orderBy('id')->get());
    }

    public function weightCategoriesIndex(PublicRegistrationFormWeightCategoryIndexRequest $request): ResourceCollection
    {
        return WeightCategoryResource::collection(WeightCategory::orderBy('value')->orderBy('id')->get());
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
