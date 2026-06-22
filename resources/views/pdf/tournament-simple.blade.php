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
            border: 2px solid #CBD5E1;
        }

        .match-block:last-child {
            border-bottom: none;
        }

        .match-table {
            width: 100%;
            border-collapse: collapse;
        }

        .col-sort {
            width: 6%;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
            font-weight: bold;
            color: #64748B;
            padding: 0 4px;
        }

        .col-left {
            width: 37%;
        }

        .col-center {
            width: 20%;
            padding: 0;
            vertical-align: top;
        }

        .col-right {
            width: 37%;
        }

        .team-name {
            font-size: 9px;
            color: #334155;
            padding: 1px 4px 2px;
            vertical-align: middle;
        }

        .team-name-right {
            text-align: right;
        }

        .athlete-red {
            background: #C94848;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
            padding: 3px 6px;
            vertical-align: middle;
        }

        .athlete-blue {
            background: #3272A8;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
            padding: 3px 6px;
            text-align: right;
            vertical-align: middle;
        }

        .center-top {
            background: #111827;
            color: #fff;
            text-align: center;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 4px 1px;
            letter-spacing: 0;
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

@forelse($tournament->matchRecords->chunk(15) as $chunkIndex => $chunk)
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
        @foreach($chunk as $matchRecord)
            <div class="match-block">
                <table class="match-table">
                    <tr>
                        <td class="col-sort" rowspan="3">{{ $matchRecord->sort }}</td>
                        <td class="col-left athlete-red">{{ $matchRecord->red_corner_team }}</td>
                        <td class="col-center center-bottom">{{ $matchRecord->scheduled_time }}</td>
                        <td class="col-right athlete-blue">{{ $matchRecord->blue_corner_team }}</td>
                    </tr>
                    <tr>
                        <td class="col-left team-name" rowspan="2">{{ $matchRecord->redCorner->full_name }}</td>
                        <td class="col-center center-top">{{ $matchRecord->discipline->label }}
                            – {{ $matchRecord->weightCategory->label }}</td>
                        <td class="col-right team-name team-name-right"
                            rowspan="2">{{ $matchRecord->blueCorner->full_name }}</td>
                    </tr>
                    <tr>
                        <td class="col-center center-top">{{ $matchRecord->rounds }}
                            x {{ $matchRecord->minutes_per_round }}</td>
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
