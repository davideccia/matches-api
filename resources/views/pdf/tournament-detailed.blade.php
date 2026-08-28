<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $tournament->name }} — Dettagliato</title>
    <style>
        /* A `*` reset zeroes out the @page margin in DOMPDF, so reset by element. */
        body, h1, table, tr, td, th, div, span {
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        html {
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #10141A;
        }

        /* ── Outer 2-column grid ──────────────────────── */

        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 7px 12px;
        }

        .grid-table > tbody > tr > td {
            width: 49%;
            vertical-align: top;
            padding: 0;
        }

        /* ── Card ─────────────────────────────────────── */

        .card-wrap {
            border: 1px solid #B4BBC3;
        }

        .card {
            width: 100%;
            border-collapse: collapse;
        }

        .card td, .card th {
            padding: 0;
        }

        /* Tournament identity: context, so it stays quiet. */
        .card-ident {
            padding: 5px 10px;
            border-bottom: 0.5px solid #DDE1E6;
            font-size: 7px;
            color: #7A828C;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .card-ident strong {
            color: #10141A;
        }

        /* ── Card header ──────────────────────────────── */

        .card-header {
            padding: 8px 10px;
            border-bottom: 1px solid #B4BBC3;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: middle;
            padding: 0;
        }

        .td-no {
            width: 9%;
            font-size: 15px;
            font-weight: bold;
            color: #C3C8CE;
        }

        .discipline-name {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.1px;
        }

        .meta-row {
            padding-top: 1px;
            font-size: 7.5px;
            color: #7A828C;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }

        .td-badge {
            width: 1%;
            white-space: nowrap;
            text-align: right;
            padding-left: 6px;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .badge-scheduled {
            border: 1px solid #B4BBC3;
            color: #7A828C;
        }

        .badge-in_progress {
            border: 1px solid #A4262C;
            color: #A4262C;
        }

        .badge-completed {
            background: #10141A;
            color: #fff;
        }

        .badge-cancelled {
            background: #7A828C;
            color: #fff;
        }

        /* ── Card body: the two corners ───────────────── */

        .card-body {
            padding: 9px 0;
        }

        .vs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vs-table td {
            vertical-align: middle;
            padding: 0;
        }

        /* Corner identity is carried by the outer edge rule + the name colour. */
        .td-red {
            width: 43%;
            border-left: 3px solid #A4262C;
        }

        .td-red div {
            padding-left: 10px;
        }

        .td-blue {
            width: 43%;
            text-align: right;
            border-right: 3px solid #12507F;
        }

        .td-blue div {
            padding-right: 10px;
        }

        .td-vs {
            width: 12%;
            text-align: center;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            color: #A8AEB6;
        }

        .corner-name {
            font-size: 11px;
            font-weight: bold;
        }

        .td-red .corner-name {
            color: #A4262C;
        }

        .td-blue .corner-name {
            color: #12507F;
        }

        /* The loser stays legible — this is an official record, not a highlight reel. */
        .corner-name-dim {
            font-size: 11px;
            font-weight: bold;
            color: #7A828C;
        }

        .corner-team {
            font-size: 7.5px;
            color: #7A828C;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding-top: 1px;
        }

        /* ── Outcome ──────────────────────────────────── */

        .outcome {
            padding: 6px 10px;
            border-top: 0.5px solid #DDE1E6;
            font-size: 7.5px;
        }

        .outcome-label {
            font-size: 6.5px;
            color: #7A828C;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .outcome-value {
            font-size: 9.5px;
            font-weight: bold;
            padding-top: 1px;
        }

        .outcome-red {
            color: #A4262C;
        }

        .outcome-blue {
            color: #12507F;
        }

        .outcome-neutral {
            color: #10141A;
        }

        .outcome-muted {
            color: #A8AEB6;
        }

        .outcome-method {
            font-size: 7px;
            color: #7A828C;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding-top: 2px;
        }

        /* ── Judges scorecard ─────────────────────────── */

        .judges-section {
            padding: 7px 10px 9px;
            border-top: 0.5px solid #DDE1E6;
        }

        .judges-title {
            font-size: 6.5px;
            color: #7A828C;
            letter-spacing: 1.4px;
            text-transform: uppercase;
            padding-bottom: 4px;
        }

        .judges-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            border: 1px solid #B4BBC3;
        }

        .judges-table th, .judges-table td {
            text-align: center;
            padding: 2px 3px;
        }

        .jt-head-1 th {
            border-bottom: 0.5px solid #DDE1E6;
            font-size: 6.5px;
            letter-spacing: 1.2px;
            text-transform: uppercase;
        }

        .jt-head-2 th {
            border-bottom: 1px solid #B4BBC3;
            font-size: 6.5px;
            font-weight: normal;
            color: #7A828C;
            letter-spacing: 0.8px;
        }

        .round-th {
            width: 22px;
            text-align: left;
            font-weight: normal;
            color: #7A828C;
        }

        .red-group {
            color: #A4262C;
        }

        .blue-group {
            color: #12507F;
        }

        .bl-strong {
            border-left: 1px solid #B4BBC3;
        }

        .bl-soft {
            border-left: 0.5px solid #DDE1E6;
        }

        .judges-table tbody tr {
            border-top: 0.5px solid #DDE1E6;
        }

        .round-cell {
            text-align: left;
            color: #7A828C;
        }

        .jt-foot tr {
            border-top: 1px solid #B4BBC3;
            font-weight: bold;
        }

        .jt-foot .round-cell {
            font-size: 6.5px;
            font-weight: normal;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── Misc ─────────────────────────────────────── */

        .empty {
            border-top: 0.5px solid #DDE1E6;
            border-bottom: 0.5px solid #DDE1E6;
            text-align: center;
            padding: 24px;
            color: #7A828C;
            font-size: 8px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .page-footer {
            margin-top: 8px;
            font-size: 7px;
            color: #A8AEB6;
            letter-spacing: 1px;
            text-transform: uppercase;
            text-align: right;
        }
    </style>
</head>
<body>

@if($tournament->matchRecords->isEmpty())
    <div class="empty">Nessun incontro registrato</div>
@else
    @php $chunks = $tournament->matchRecords->chunk(2); @endphp
    <table class="grid-table">
        <tbody>
        @foreach($chunks as $pair)
            <tr>
                @foreach($pair as $match)
                    @php
                        $status       = $match->status?->value ?? 'scheduled';
                        $isRedWinner  = $match->winner_id && $match->winner_id === $match->red_corner_id;
                        $isBlueWinner = $match->winner_id && $match->winner_id === $match->blue_corner_id;
                        $isDraw       = $match->end_method?->value === 'draw';
                        $isCancelled  = $status === 'cancelled';
                        $isCompleted  = $status === 'completed';
                        $hasWinner    = $isCompleted && $match->winner_id && ! $isDraw && ! $isCancelled;
                        $redDim       = $isCompleted && !$isCancelled && !$isRedWinner && !$isDraw;
                        $blueDim      = $isCompleted && !$isCancelled && !$isBlueWinner && !$isDraw;
                    @endphp
                    <td>
                        <div class="card-wrap">
                            <table class="card">
                                {{-- ── Tournament identity ── --}}
                                <tr>
                                    <td class="card-ident">
                                        <strong>{{ $tournament->name }}</strong>
                                        &middot; {{ $tournament->date_from?->format('d/m/Y') }}
                                        @if($tournament->date_to && ! $tournament->date_to->isSameDay($tournament->date_from)) &ndash; {{ $tournament->date_to->format('d/m/Y') }}@endif
                                        @if($tournament->location_name) &middot; {{ $tournament->location_name }}@endif
                                        @if($tournament->location_city) &middot; {{ $tournament->location_city }}@endif
                                    </td>
                                </tr>

                                {{-- ── Card header ── --}}
                                <tr>
                                    <td class="card-header">
                                        <table class="header-table">
                                            <tr>
                                                <td class="td-no">{{ $match->sort }}</td>
                                                <td>
                                                    <div class="discipline-name">{{ $match->discipline?->label ?? '—' }}</div>
                                                    <div class="meta-row">
                                                        {{ $match->weightCategory?->label ?? '—' }}
                                                        @if($match->rounds && $match->minutes_per_round)
                                                            &middot; {{ $match->rounds }}&times;{{ $match->minutes_per_round }}
                                                        @endif
                                                        @if($match->gender)
                                                            &middot; {{ $match->gender->label() }}
                                                        @endif
                                                        @if($match->scheduled_time)
                                                            &middot; {{ \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') }}
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="td-badge">
                                                    <span class="badge badge-{{ $status }}">
                                                        {{ $match->status?->label() ?? $status }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- ── Card body: the two corners ── --}}
                                <tr>
                                    <td class="card-body">
                                        <table class="vs-table">
                                            <tr>
                                                <td class="td-red">
                                                    <div class="{{ $redDim ? 'corner-name-dim' : 'corner-name' }}">{{ $match->redCorner?->full_name ?? '—' }}</div>
                                                    @if($match->red_corner_team)
                                                        <div class="corner-team">{{ $match->red_corner_team }}</div>
                                                    @endif
                                                </td>
                                                <td class="td-vs">vs</td>
                                                <td class="td-blue">
                                                    <div class="{{ $blueDim ? 'corner-name-dim' : 'corner-name' }}">{{ $match->blueCorner?->full_name ?? '—' }}</div>
                                                    @if($match->blue_corner_team)
                                                        <div class="corner-team">{{ $match->blue_corner_team }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- ── Outcome ── --}}
                                <tr>
                                    <td class="outcome">
                                        @if($isCancelled)
                                            <div class="outcome-label">Esito</div>
                                            <div class="outcome-value outcome-muted">Annullato</div>
                                        @elseif($hasWinner)
                                            <div class="outcome-label">Vincitore</div>
                                            <div class="outcome-value {{ $isRedWinner ? 'outcome-red' : 'outcome-blue' }}">
                                                {{ $match->winner?->full_name ?? '—' }}
                                                @if($match->end_round) (R{{ $match->end_round }})@endif
                                            </div>
                                        @elseif($isDraw)
                                            <div class="outcome-label">Esito</div>
                                            <div class="outcome-value outcome-neutral">Pareggio</div>
                                        @else
                                            <div class="outcome-label">Esito</div>
                                            <div class="outcome-value outcome-muted">In attesa</div>
                                        @endif

                                        @if($match->end_method)
                                            <div class="outcome-method">Metodo &middot; {{ $match->end_method->label() }}</div>
                                        @endif
                                    </td>
                                </tr>

                                {{-- ── Judges scorecard ── --}}
                                @if(!empty($match->judges_points))
                                    @php
                                        $rounds = $match->judges_points;
                                        $tot = ['j1r' => 0, 'j2r' => 0, 'j3r' => 0, 'j1b' => 0, 'j2b' => 0, 'j3b' => 0];
                                        foreach ($rounds as $r) {
                                            $tot['j1r'] += $r['judge1_red']  ?? 0;
                                            $tot['j2r'] += $r['judge2_red']  ?? 0;
                                            $tot['j3r'] += $r['judge3_red']  ?? 0;
                                            $tot['j1b'] += $r['judge1_blue'] ?? 0;
                                            $tot['j2b'] += $r['judge2_blue'] ?? 0;
                                            $tot['j3b'] += $r['judge3_blue'] ?? 0;
                                        }
                                    @endphp
                                    <tr>
                                        <td class="judges-section">
                                            <div class="judges-title">Punti giudici</div>
                                            <table class="judges-table">
                                                <thead>
                                                <tr class="jt-head-1">
                                                    <th class="round-th" rowspan="2">R</th>
                                                    <th colspan="3" class="red-group bl-strong">Rosso</th>
                                                    <th colspan="3" class="blue-group bl-strong">Blu</th>
                                                </tr>
                                                <tr class="jt-head-2">
                                                    <th class="bl-strong">G1</th>
                                                    <th class="bl-soft">G2</th>
                                                    <th class="bl-soft">G3</th>
                                                    <th class="bl-strong">G1</th>
                                                    <th class="bl-soft">G2</th>
                                                    <th class="bl-soft">G3</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                @foreach($rounds as $r)
                                                    <tr>
                                                        <td class="round-cell">{{ $r['round'] }}</td>
                                                        <td class="bl-strong">{{ $r['judge1_red']  ?? '—' }}</td>
                                                        <td class="bl-soft">{{ $r['judge2_red']   ?? '—' }}</td>
                                                        <td class="bl-soft">{{ $r['judge3_red']   ?? '—' }}</td>
                                                        <td class="bl-strong">{{ $r['judge1_blue'] ?? '—' }}</td>
                                                        <td class="bl-soft">{{ $r['judge2_blue']  ?? '—' }}</td>
                                                        <td class="bl-soft">{{ $r['judge3_blue']  ?? '—' }}</td>
                                                    </tr>
                                                @endforeach
                                                </tbody>
                                                <tfoot class="jt-foot">
                                                <tr>
                                                    <td class="round-cell">Tot</td>
                                                    <td class="bl-strong">{{ $tot['j1r'] }}</td>
                                                    <td class="bl-soft">{{ $tot['j2r'] }}</td>
                                                    <td class="bl-soft">{{ $tot['j3r'] }}</td>
                                                    <td class="bl-strong">{{ $tot['j1b'] }}</td>
                                                    <td class="bl-soft">{{ $tot['j2b'] }}</td>
                                                    <td class="bl-soft">{{ $tot['j3b'] }}</td>
                                                </tr>
                                                </tfoot>
                                            </table>
                                        </td>
                                    </tr>
                                @endif

                            </table>
                        </div>
                    </td>
                @endforeach

                {{-- Pad with an empty cell if the row holds a single match --}}
                @if($pair->count() === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="page-footer">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
