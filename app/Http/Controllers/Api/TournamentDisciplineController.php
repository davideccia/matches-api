<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discipline\DisciplineIndexRequest;
use App\Http\Resources\DisciplineResource;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TournamentDisciplineController extends Controller
{
    public function index(DisciplineIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        $disciplines = $tournament->disciplines()->with($validated['with'] ?? []);

        if (isset($validated['search'])) {
            $disciplines->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $disciplines = $disciplines->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $disciplines = $disciplines->get();
        }

        return DisciplineResource::collection($disciplines);
    }
}
