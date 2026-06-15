<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WeightCategory\WeightCategoryDestroyRequest;
use App\Http\Requests\WeightCategory\WeightCategoryIndexRequest;
use App\Http\Requests\WeightCategory\WeightCategoryShowRequest;
use App\Http\Requests\WeightCategory\WeightCategoryStoreRequest;
use App\Http\Requests\WeightCategory\WeightCategoryUpdateRequest;
use App\Http\Resources\WeightCategoryResource;
use App\Models\WeightCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WeightCategoryController extends Controller
{
    public function index(WeightCategoryIndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        $weightCategories = WeightCategory::with($validated['with'] ?? []);

        if (isset($validated['search'])) {
            $weightCategories->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $weightCategories = $weightCategories->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $weightCategories = $weightCategories->get();
        }

        return WeightCategoryResource::collection($weightCategories);
    }

    public function store(WeightCategoryStoreRequest $request): WeightCategoryResource
    {
        $validated = $request->validated();

        $weightCategory = new WeightCategory;
        $weightCategory->fill($validated)->saveOrFail();

        return new WeightCategoryResource($weightCategory->loadMissing($validated['with'] ?? []));
    }

    public function show(WeightCategoryShowRequest $request, WeightCategory $weightCategory): WeightCategoryResource
    {
        $validated = $request->validated();

        return new WeightCategoryResource($weightCategory->loadMissing($validated['with'] ?? []));
    }

    public function update(WeightCategoryUpdateRequest $request, WeightCategory $weightCategory): WeightCategoryResource
    {
        $validated = $request->validated();

        $weightCategory->fill($validated)->saveOrFail();

        return new WeightCategoryResource($weightCategory->loadMissing($validated['with'] ?? []));
    }

    public function destroy(WeightCategoryDestroyRequest $request, WeightCategory $weightCategory): JsonResponse
    {
        $weightCategory->delete();

        return response()->json([], 204);
    }
}
