<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegistrationDestroyRequest;
use App\Http\Requests\Registration\RegistrationIndexRequest;
use App\Http\Requests\Registration\RegistrationShowRequest;
use App\Http\Requests\Registration\RegistrationStoreRequest;
use App\Http\Requests\Registration\RegistrationUpdateRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Registration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class RegistrationController extends Controller
{
    public function index(RegistrationIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $registrations = Registration::with($validated['with'] ?? []);

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
}
