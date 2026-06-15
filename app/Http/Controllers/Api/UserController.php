<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserDestroyRequest;
use App\Http\Requests\User\UserIndexRequest;
use App\Http\Requests\User\UserShowRequest;
use App\Http\Requests\User\UserStoreRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserController extends Controller
{
    public function index(UserIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $users = User::with($validated['with'] ?? []);

        if ($validated['paginate'] ?? false) {
            $users = $users->paginate($validated['limit'] ?? null);
        } else {
            $users = $users->get();
        }

        return UserResource::collection($users);
    }

    public function store(UserStoreRequest $request): UserResource
    {
        $validated = $request->validated();

        $user = new User;
        $user->fill($validated)->saveOrFail();

        return new UserResource($user->loadMissing($validated['with'] ?? []));
    }

    public function show(UserShowRequest $request, User $user): UserResource
    {
        $validated = $request->validated();

        return new UserResource($user->loadMissing($validated['with'] ?? []));
    }

    public function update(UserUpdateRequest $request, User $user): UserResource
    {
        $validated = $request->validated();

        $user->fill($validated)->saveOrFail();

        return new UserResource($user->loadMissing($validated['with'] ?? []));
    }

    public function destroy(UserDestroyRequest $request, User $user): JsonResponse
    {
        $user->delete();

        return response()->json([], 204);
    }
}
