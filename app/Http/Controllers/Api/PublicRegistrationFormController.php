<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormAthleteLookupRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormDisciplineIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationPdfRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormRegistrationStoreRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormTournamentIndexRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormVerificationCodeRequest;
use App\Http\Requests\PublicRegistrationForm\PublicRegistrationFormWeightCategoryIndexRequest;
use App\Http\Resources\DisciplineResource;
use App\Http\Resources\Public\PublicRegistrationAthleteResource;
use App\Http\Resources\Public\PublicRegistrationResource;
use App\Http\Resources\Public\PublicTournamentResource;
use App\Http\Resources\WeightCategoryResource;
use App\Models\Athlete;
use App\Models\Discipline;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\WeightCategory;
use App\Support\RegistrationVerificationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

class PublicRegistrationFormController extends Controller
{
    public function lookupAthlete(PublicRegistrationFormAthleteLookupRequest $request): PublicRegistrationAthleteResource
    {
        $validated = $request->validated();

        $athlete = Athlete::where('tax_number', Athlete::normalizeTaxNumber($validated['tax_number']))->first();

        if ($athlete === null || ! $athlete->emailMatches($validated['email'])) {
            Log::warning('public.athlete_lookup_failed', ['ip' => $request->ip()]);

            abort(400, __('errors.athlete_lookup_failed'));
        }

        return new PublicRegistrationAthleteResource($athlete);
    }

    public function requestVerificationCode(PublicRegistrationFormVerificationCodeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        RegistrationVerificationCode::issue($validated['tax_number'], $validated['email']);

        return response()->json(null, 204);
    }

    public function tournamentsIndex(PublicRegistrationFormTournamentIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $tournaments = Tournament::where('status', TournamentStatusEnum::REGISTRATIONS_OPENED)
            ->orderBy('date_from')
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

    public function storeRegistration(PublicRegistrationFormRegistrationStoreRequest $request): PublicRegistrationResource
    {
        $validated = $request->validated();

        if (! RegistrationVerificationCode::verify($validated['tax_number'], $validated['code'])) {

            Log::warning('public.registration_verification_failed', ['ip' => $request->ip()]);

            abort(400, __('errors.registration_verification_code_invalid'));
        }

        $athlete = Athlete::where('tax_number', Athlete::normalizeTaxNumber($validated['tax_number']))->first();

        if ($athlete !== null && ! $athlete->emailMatches($validated['email'])) {
            abort(400, __('errors.athlete_lookup_failed'));
        }

        $registration = DB::transaction(static function () use ($validated, $athlete): Registration {
            // An existing athlete is never overwritten: letting the payload win
            // would turn this endpoint into an account takeover.
            $athlete ??= Athlete::create(Arr::only($validated, [
                'first_name', 'last_name', 'birth_date', 'gender', 'tax_number', 'email', 'team_name',
            ]));

            $registration = new Registration;
            $registration->fill([
                'athlete_id' => $athlete->id,
                ...Arr::only($validated, ['tournament_id', 'discipline_id', 'weight_category_id']),
            ])->saveOrFail();

            return $registration;
        });

        return new PublicRegistrationResource(
            $registration->loadMissing(['tournament', 'discipline', 'weightCategory'])
        );
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
