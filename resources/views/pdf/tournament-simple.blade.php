<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $tournament->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 14mm 20mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #1E293B;
        }

        .header {
            text-align: center;
            margin-bottom: 18px;
        }

        .header h1 {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }

        .header .meta {
            font-size: 9px;
            color: #64748B;
            margin-top: 4px;
        }

        .matches-outer {
            width: 80%;
            margin: 0 auto;
            border: 2px solid #1E293B;
        }

        .match-block {
            padding: 8px 10px;
            border-bottom: 1px solid #CBD5E1;
        }

        .match-block:last-child {
            border-bottom: none;
        }

        .match-table {
            width: 100%;
            border-collapse: collapse;
        }

        .col-left {
            width: 40%;
        }

        .col-center {
            width: 20%;
        }

        .col-right {
            width: 40%;
        }

        .team-name {
            font-size: 8.5px;
            color: #334155;
            padding: 1px 4px 2px;
        }

        .team-name-right {
            text-align: right;
        }

        .athlete-cell {
            padding: 0;
        }

        .athlete-red {
            background: #C94848;
            color: #fff;
            font-weight: bold;
            font-size: 9.5px;
            padding: 3px 6px;
        }

        .athlete-blue {
            background: #3272A8;
            color: #fff;
            font-weight: bold;
            font-size: 9.5px;
            padding: 3px 6px;
            text-align: right;
        }

        .center-top {
            background: #111827;
            color: #fff;
            text-align: center;
            font-size: 8.5px;
            font-weight: bold;
            padding: 2px 4px 1px;
            letter-spacing: 0.3px;
        }

        .center-bottom {
            background: #111827;
            color: #D1D5DB;
            text-align: center;
            font-size: 8px;
            padding: 1px 4px 3px;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: #94A3B8;
            font-size: 9px;
        }

        .footer {
            margin-top: 12px;
            font-size: 8px;
            color: #94A3B8;
            text-align: center;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>{{ $tournament->name }}</h1>
    <div class="meta">
        {{ $tournament->date?->format('d/m/Y') }}
        @if($tournament->location_name) &mdash; {{ $tournament->location_name }}@endif
        @if($tournament->location_city)
            ({{ $tournament->location_city }})
        @endif
    </div>
</div>

@forelse($tournament->matchRecords->chunk(17) as $chunkIndex => $chunk)
    @if($chunkIndex > 0)
        <div class="page-break"></div>
        <div class="header">
            <h1>{{ $tournament->name }}</h1>
            <div class="meta">
                {{ $tournament->date?->format('d/m/Y') }}
                @if($tournament->location_name) &mdash; {{ $tournament->location_name }}@endif
                @if($tournament->location_city)
                    ({{ $tournament->location_city }})
                @endif
            </div>
        </div>
    @endif

    <div class="matches-outer">
        @foreach($chunk as $match)
            <div class="match-block">
                <table class="match-table">
                    <tr>
                        <td class="col-left team-name">{{ $match->red_corner_team }}</td>
                        <td class="col-center center-top">{{ $match->discipline->label }}</td>
                        <td class="col-right team-name team-name-right">{{ $match->blue_corner_team }}</td>
                    </tr>
                    <tr>
                        <td class="col-left athlete-cell">
                            <div class="athlete-red">{{ $match->redCorner->full_name }}</div>
                        </td>
                        <td class="col-center">
                            <div class="center-top">{{ $match->weightCategory->label }}</div>
                            <div class="center-bottom">{{ $match->rounds }} x {{ $match->minutes_per_round }}</div>
                        </td>
                        <td class="col-right athlete-cell">
                            <div class="athlete-blue">{{ $match->blueCorner->full_name }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>

    <div class="footer">Generato il {{ now()->format('d/m/Y H:i') }}</div>
@empty
    <div class="matches-outer">
        <div class="empty-state">Nessun match registrato</div>
    </div>
@endforelse

</body>
</html>
