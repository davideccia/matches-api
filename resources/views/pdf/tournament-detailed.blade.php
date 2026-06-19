<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $tournament->name }} — Dettagliato</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm 12mm;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
        }

        /* ── Tournament info inside card ─────────────── */
        .tournament-info-row {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .tournament-info-name {
            font-weight: 700;
            font-size: 11px;
            color: #111827;
        }

        .tournament-info-meta {
            font-size: 9px;
            color: #6b7280;
            margin-top: 1px;
        }

        /* ── Outer 2-column grid ──────────────────────── */
        .grid-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 16px;
        }

        .grid-table > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }

        /* ── Card wrapper ─────────────────────────────── */
        .card-wrap {
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
        }

        .card {
            width: 100%;
            border-collapse: collapse;
        }

        .card td, .card th {
            padding: 0;
        }

        /* ── Card header ──────────────────────────────── */
        .card-header {
            padding: 10px 12px;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-badge-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-badge-table td {
            vertical-align: top;
            padding: 0;
        }

        .td-badge {
            width: 1%;
            white-space: nowrap;
            padding-left: 6px;
        }

        .discipline-name {
            font-weight: 700;
            font-size: 11px;
            color: #111827;
        }

        .meta-row {
            margin-top: 2px;
            font-size: 9px;
            color: #6b7280;
        }

        .header-meta {
            margin-top: 4px;
            font-size: 9px;
            color: #6b7280;
        }

        .badge {
            display: inline-block;
            padding: 1px 7px;
            font-size: 9px;
            font-weight: 700;
            white-space: nowrap;
        }

        .badge-scheduled {
            border: 1.5px solid #3b82f6;
            color: #2563eb;
        }

        .badge-in_progress {
            background: #f59e0b;
            color: #fff;
        }

        .badge-completed {
            background: #22c55e;
            color: #fff;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        /* ── Card body: VS layout ─────────────────────── */
        .card-body {
            padding: 10px 12px;
        }

        .vs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .vs-table td {
            vertical-align: middle;
            padding: 2px 0;
        }

        .td-red {
            width: 44%;
        }

        .td-vs {
            width: 12%;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #9ca3af;
        }

        .td-blue {
            width: 44%;
            text-align: right;
        }

        .corner-name {
            font-weight: 700;
            font-size: 11px;
            color: #111827;
        }

        .corner-name-dim {
            font-weight: 700;
            font-size: 11px;
            color: #c8c8c8;
        }

        .corner-team {
            font-size: 9px;
            color: #9ca3af;
        }

        .dot {
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 4px;
            vertical-align: middle;
        }

        .dot-red {
            background: #ef4444;
        }

        .dot-blue {
            background: #3b82f6;
        }

        /* result banner row */
        .result-cell {
            text-align: center;
            font-size: 9px;
            font-weight: 600;
            padding: 3px 8px;
        }

        .result-winner {
            background: #dcfce7;
            color: #16a34a;
        }

        .result-draw {
            background: #f3f4f6;
            color: #6b7280;
        }

        .result-cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        .result-pending {
            color: #9ca3af;
        }

        /* ── Card footer ──────────────────────────────── */
        .card-footer {
            padding: 8px 12px;
            border-top: 1px solid #e5e7eb;
            font-size: 9px;
            color: #6b7280;
        }

        .footer-method {
            font-weight: 600;
            color: #374151;
        }

        /* ── Judges table ─────────────────────────────── */
        .judges-section {
            padding: 8px 12px 10px;
            border-top: 1px solid #e5e7eb;
        }

        .judges-title {
            font-size: 9px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }

        .judges-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            border: 1px solid #e5e7eb;
        }

        .judges-table th, .judges-table td {
            text-align: center;
            padding: 2px 4px;
        }

        .jt-head-1 th {
            background: #f9fafb;
            border-bottom: 1px solid #e5e7eb;
        }

        .jt-head-2 th {
            background: #f3f4f6;
            border-bottom: 1px solid #e5e7eb;
        }

        .round-th {
            text-align: left;
            font-weight: 500;
            color: #9ca3af;
            width: 24px;
        }

        .red-group {
            color: #ef4444;
            font-weight: 700;
        }

        .blue-group {
            color: #3b82f6;
            font-weight: 700;
        }

        .sub {
            font-weight: 500;
            color: #9ca3af;
        }

        .bl-strong {
            border-left: 1px solid #d1d5db;
        }

        .bl-soft {
            border-left: 1px solid #e5e7eb;
        }

        .judges-table tbody tr {
            border-top: 1px solid #f3f4f6;
        }

        .round-cell {
            font-weight: 600;
            color: #9ca3af;
            text-align: left;
            padding-left: 4px;
        }

        .jt-foot tr {
            border-top: 1.5px solid #e5e7eb;
            background: #f9fafb;
            font-weight: 700;
        }

        /* ── Misc ─────────────────────────────────────── */
        .empty {
            text-align: center;
            padding: 24px;
            color: #94A3B8;
            font-size: 10px;
        }

        .page-footer {
            margin-top: 8px;
            font-size: 8px;
            color: #94A3B8;
            text-align: right;
        }
    </style>
</head>
<body>


@if($tournament->matchRecords->isEmpty())
    <div class="empty">Nessun match registrato</div>
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
                        $redDim       = $isCompleted && !$isCancelled && !$isRedWinner && !$isDraw;
                        $blueDim      = $isCompleted && !$isCancelled && !$isBlueWinner && !$isDraw;
                    @endphp
                    <td>
                        <div class="card-wrap">
                            <table class="card">
                                {{-- ── Tournament info ── --}}
                                <tr>
                                    <td class="tournament-info-row">
                                        <div class="tournament-info-name">{{ $tournament->name }}</div>
                                        <div class="tournament-info-meta">
                                            {{ $tournament->date?->format('d/m/Y') }}
                                            @if($tournament->location_name)
                                                &nbsp;—&nbsp;{{ $tournament->location_name }}
                                            @endif
                                            @if($tournament->location_city)
                                                ({{ $tournament->location_city }})
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- ── Card header ── --}}
                                <tr>
                                    <td class="card-header">
                                        <table class="info-badge-table">
                                            <tr>
                                                <td>
                                                    <div
                                                        class="discipline-name">{{ $match->discipline?->label ?? '—' }}</div>
                                                    <div class="meta-row">
                                                        {{ $match->weightCategory?->label ?? '—' }}
                                                        @if($match->rounds && $match->minutes_per_round)
                                                            &nbsp;·&nbsp;{{ $match->rounds }}
                                                            ×{{ $match->minutes_per_round }}'
                                                        @endif
                                                        @if($match->gender)
                                                            &nbsp;·&nbsp;{{ $match->gender->label() }}
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
                                        <div class="header-meta">
                                            @if($match->scheduled_time)
                                                {{ \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') }}&nbsp;
                                                &nbsp;
                                            @endif
                                            #&nbsp;{{ $match->sort }}
                                        </div>
                                    </td>
                                </tr>

                                {{-- ── Card body: VS ── --}}
                                <tr>
                                    <td class="card-body">
                                        <table class="vs-table">
                                            <tr>
                                                {{-- Angolo rosso --}}
                                                <td class="td-red">
                                                    <div class="{{ $redDim ? 'corner-name-dim' : 'corner-name' }}">
                                                        <span
                                                            class="dot dot-red"></span>&nbsp;{{ $match->redCorner?->full_name ?? '—' }}
                                                    </div>
                                                    @if($match->red_corner_team)
                                                        <div class="corner-team">{{ $match->red_corner_team }}</div>
                                                    @endif
                                                </td>
                                                <td class="td-vs">vs</td>
                                                {{-- Angolo blu --}}
                                                <td class="td-blue">
                                                    <div class="{{ $blueDim ? 'corner-name-dim' : 'corner-name' }}">
                                                        {{ $match->blueCorner?->full_name ?? '—' }}&nbsp;<span
                                                            class="dot dot-blue"></span>
                                                    </div>
                                                    @if($match->blue_corner_team)
                                                        <div class="corner-team"
                                                             style="text-align:right;">{{ $match->blue_corner_team }}</div>
                                                    @endif
                                                </td>
                                            </tr>
                                            {{-- Result banner --}}
                                            <tr>
                                                <td colspan="3"
                                                    class="result-cell {{ $isCancelled ? 'result-cancelled' : ($isCompleted && $match->winner_id && !$isDraw ? 'result-winner' : ($isDraw ? 'result-draw' : 'result-pending')) }}">
                                                    @if($isCancelled)
                                                        Annullato
                                                    @elseif($isCompleted && $match->winner_id && !$isDraw)
                                                        {{ $match->winner?->full_name ?? '—' }}@if($match->end_round)
                                                            &nbsp;(R{{ $match->end_round }})
                                                        @endif
                                                    @elseif($isDraw)
                                                        Pareggio
                                                    @else
                                                        In attesa
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- ── Card footer: end method ── --}}
                                @if($match->end_method)
                                    <tr>
                                        <td class="card-footer">
                                            Metodo: <span class="footer-method">{{ $match->end_method->label() }}</span>
                                        </td>
                                    </tr>
                                @endif

                                {{-- ── Judges points ── --}}
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
                                                    <th class="sub bl-strong">G1</th>
                                                    <th class="sub bl-soft">G2</th>
                                                    <th class="sub bl-soft">G3</th>
                                                    <th class="sub bl-strong">G1</th>
                                                    <th class="sub bl-soft">G2</th>
                                                    <th class="sub bl-soft">G3</th>
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
                                                    <td class="round-cell" style="font-size:8px;color:#9ca3af;">Tot</td>
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

                {{-- Pad with empty cell if odd number of matches --}}
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
