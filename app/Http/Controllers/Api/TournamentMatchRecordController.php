<?php

namespace App\Http\Controllers\Api;

use App\Enums\TournamentPdfTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\MatchRecord\MatchRecordIndexRequest;
use App\Http\Requests\MatchRecord\MatchRecordStoreRequest;
use App\Http\Requests\Tournament\TournamentGenerateMatchRecordsRequest;
use App\Http\Requests\Tournament\TournamentMatchRecordsPdfRequest;
use App\Http\Resources\MatchRecordResource;
use App\Models\MatchRecord;
use App\Models\Tournament;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

class TournamentMatchRecordController extends Controller
{
    public function index(MatchRecordIndexRequest $request, Tournament $tournament): ResourceCollection
    {
        $validated = $request->validated();

        $matchRecords = MatchRecord::with($validated['with'] ?? [])
            ->where('tournament_id', $tournament->id);

        if (isset($validated['search'])) {
            $matchRecords->search($validated['search']);
        }

        if ($validated['paginate'] ?? false) {
            $matchRecords = $matchRecords->paginate(($validated['per_page'] ?? null), ['*'], 'page', ($validated['page'] ?? null));
        } else {
            $matchRecords = $matchRecords->get();
        }

        return MatchRecordResource::collection($matchRecords);
    }

    public function store(MatchRecordStoreRequest $request, Tournament $tournament): MatchRecordResource
    {
        $validated = $request->validated();

        $matchRecord = new MatchRecord;
        $matchRecord->fill($validated)->saveOrFail();

        return new MatchRecordResource($matchRecord->loadMissing($validated['with'] ?? []));
    }

    public function generateMatchRecords(TournamentGenerateMatchRecordsRequest $request, Tournament $tournament): ResourceCollection
    {
        $tournament->runMatchmaking();

        return MatchRecordResource::collection($tournament->matchRecords()->get());
    }

    public function matchRecordsPdf(TournamentMatchRecordsPdfRequest $request, Tournament $tournament): PdfBuilder
    {
        $validated = $request->validated();

        $tournament->loadMissing([
            'matchRecords.redCorner',
            'matchRecords.blueCorner',
            'matchRecords.discipline',
            'matchRecords.weightCategory',
            'matchRecords.winner',
        ]);

        $type = TournamentPdfTypeEnum::from($validated['type']);

        return pdf()
            ->view($type->viewName(), ['tournament' => $tournament])
            ->format(Format::A4)
            ->name("tournament-{$tournament->id}-{$type->value}.pdf");
    }
}
