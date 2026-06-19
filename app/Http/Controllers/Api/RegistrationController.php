<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegistrationDestroyRequest;
use App\Http\Requests\Registration\RegistrationIndexRequest;
use App\Http\Requests\Registration\RegistrationPdfRequest;
use App\Http\Requests\Registration\RegistrationShowRequest;
use App\Http\Requests\Registration\RegistrationStoreRequest;
use App\Http\Requests\Registration\RegistrationUpdateRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

class RegistrationController extends Controller
{
    public function index(RegistrationIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $registrations = Registration::with($validated['with'] ?? [])->orderByDesc('registrations.created_at')->orderByDesc('registrations.id');

        if (isset($validated['search'])) {
            $registrations->search($validated['search']);
        }

        if (isset($validated['tournament_id'])) {
            $registrations->where('registrations.tournament_id', $validated['tournament_id']);
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

        if ($validated['paginate'] ?? false) {
            $registrations = $registrations->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $registrations = $registrations->get();
        }

        return RegistrationResource::collection($registrations);
    }

    public function store(RegistrationStoreRequest $request): RegistrationResource
    {
        $validated = $request->validated();

        $registration = new Registration;
        $registration->fill($validated)->saveOrFail();

        return new RegistrationResource($registration->loadMissing($validated['with'] ?? []));
    }

    public function show(RegistrationShowRequest $request, Registration $registration): RegistrationResource
    {
        $validated = $request->validated();

        return new RegistrationResource($registration->loadMissing($validated['with'] ?? []));
    }

    public function update(RegistrationUpdateRequest $request, Registration $registration): RegistrationResource
    {
        $validated = $request->validated();

        $registration->fill($validated)->saveOrFail();

        return new RegistrationResource($registration->loadMissing($validated['with'] ?? []));
    }

    public function destroy(RegistrationDestroyRequest $request, Registration $registration): JsonResponse
    {
        $registration->delete();

        return response()->json([], 204);
    }

    public function pdf(RegistrationPdfRequest $request, Registration $registration): PdfBuilder
    {
        $registration->loadMissing(['athlete', 'tournament', 'discipline', 'weightCategory']);

        return pdf()
            ->view('pdf.registration', ['registration' => $registration])
            ->name("registration-{$registration->id}.pdf");
    }
}
