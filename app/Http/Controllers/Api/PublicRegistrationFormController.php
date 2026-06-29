<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormDisciplineIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationPdfRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormShowRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormTournamentIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormWeightCategoryIndexRequest;
use App\Http\Resources\DisciplineResource;
use App\Http\Resources\Public\PublicRegistrationAthleteResource;
use App\Http\Resources\Public\PublicTournamentResource;
use App\Http\Resources\RegistrationResource;
use App\Http\Resources\WeightCategoryResource;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

class PublicRegistrationFormController extends Controller
{
    public function showAthlete(PublicRegistrationFormShowRequest $request, Athlete $athlete): PublicRegistrationAthleteResource
    {
        return new PublicRegistrationAthleteResource($athlete);
    }

    public function storeAthlete(PublicRegistrationFormStoreRequest $request): PublicRegistrationAthleteResource
    {
        $validated = $request->validated();

        $athlete = Athlete::firstOrCreate(
            ['tax_number' => $validated['tax_number']],
            $validated,
        );

        return new PublicRegistrationAthleteResource($athlete);
    }

    public function tournamentsIndex(PublicRegistrationFormTournamentIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $tournaments = Tournament::where('status', TournamentStatusEnum::REGISTRATIONS_OPENED)
            ->orderBy('date')
            ->orderBy('id');

        if (isset($validated['search'])) {
            $tournaments->search($validated['search']);
        }

        return PublicTournamentResource::collection($tournaments->get());
    }

    public function disciplinesIndex(PublicRegistrationFormDisciplineIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $disciplines = Discipline::orderBy('label')->orderBy('id');

        if (isset($validated['search'])) {
            $disciplines->search($validated['search']);
        }

        return DisciplineResource::collection($disciplines->get());
    }

    public function weightCategoriesIndex(PublicRegistrationFormWeightCategoryIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $weightCategories = WeightCategory::orderBy('label')->orderBy('id');

        if (isset($validated['search'])) {
            $weightCategories->search($validated['search']);
        }

        return WeightCategoryResource::collection($weightCategories->get());
    }

    public function storeRegistration(PublicRegistrationFormRegistrationStoreRequest $request): RegistrationResource
    {
        $validated = $request->validated();

        $registration = new Registration;
        $registration->fill($validated)->saveOrFail();

        return new RegistrationResource($registration);
    }

    public function registrationPdf(PublicRegistrationFormRegistrationPdfRequest $request, Registration $registration): PdfBuilder
    {
        $registration->loadMissing(['athlete', 'tournament', 'discipline', 'weightCategory']);

        return pdf()
            ->view('pdf.registration', compact('registration'))

            ->format(Format::A4)
            ->name("registration-{$registration->id}.pdf");
    }
}
