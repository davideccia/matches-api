<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $tournament->name }}</title>
    <style>
        /* A `*` reset zeroes out the @page margin in DOMPDF, so reset by element. */
        body, h1, table, tr, td, div {
            margin: 0;
            padding: 0;
        }

        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #10141A;
        }

        /* ---- masthead ---- */

        .masthead {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1.5px solid #10141A;
            padding-bottom: 5px;
        }

        .masthead h1 {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: -0.2px;
        }

        .masthead .place {
            font-size: 8px;
            color: #7A828C;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding-top: 3px;
        }

        .masthead .stamp {
            text-align: right;
            vertical-align: bottom;
            font-size: 7.5px;
            color: #7A828C;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .masthead .stamp strong {
            color: #10141A;
        }

        /* ---- column legend ---- */

        .legend {
            width: 100%;
            border-collapse: collapse;
            font-size: 6.5px;
            color: #7A828C;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .legend td {
            padding: 4px 0 5px;
            border-bottom: 1px solid #B4BBC3;
        }

        /* ---- bouts ---- */

        .bouts {
            width: 100%;
            border-collapse: collapse;
        }

        .bouts td {
            border-bottom: 1px solid #B4BBC3;
            padding: 10px 0;
            vertical-align: middle;
        }

        .no {
            width: 6%;
            font-size: 15px;
            font-weight: bold;
            color: #C3C8CE;
            text-align: left;
        }

        /* Corner identity is carried by the outer edge rule + the name colour. */
        .corner {
            width: 34%;
        }

        .corner-red {
            border-left: 3px solid #A4262C;
        }

        .corner-red div {
            padding-left: 8px;
        }

        .corner-blue {
            border-right: 3px solid #12507F;
            text-align: right;
        }

        .corner-blue div {
            padding-right: 8px;
        }

        .athlete {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.1px;
        }

        .corner-red .athlete {
            color: #A4262C;
        }

        .corner-blue .athlete {
            color: #12507F;
        }

        .team {
            font-size: 7.5px;
            color: #7A828C;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            padding-top: 1px;
        }

        /* The spine: the ring centre, holding everything technical about the bout. */
        .spine {
            width: 24%;
            border-left: 0.5px solid #DDE1E6;
            border-right: 0.5px solid #DDE1E6;
            text-align: center;
        }

        .spine div {
            padding-left: 6px;
            padding-right: 6px;
        }

        .spine .class {
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        .spine .format {
            font-size: 8px;
            color: #7A828C;
            letter-spacing: 0.8px;
            padding-top: 1px;
        }

        .empty-state {
            border-top: 0.5px solid #DDE1E6;
            border-bottom: 0.5px solid #DDE1E6;
            text-align: center;
            padding: 24px;
            color: #7A828C;
            font-size: 8px;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }

        .footer {
            margin-top: 10px;
            font-size: 7px;
            color: #A8AEB6;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

@php
    $chunks = $tournament->matchRecords->chunk(20);
    $chunks = $chunks->isEmpty() ? collect([collect()]) : $chunks;
    $pageCount = $chunks->count();
@endphp

@foreach($chunks as $chunkIndex => $chunk)
    @if($chunkIndex > 0)
        <div class="page-break"></div>
    @endif

    <table class="masthead">
        <tr>
            <td>
                <h1>{{ $tournament->name }}</h1>
                <div class="place">
                    {{ $tournament->date?->format('d/m/Y') }}
                    @if($tournament->location_name) &middot; {{ $tournament->location_name }}@endif
                    @if($tournament->location_city) &middot; {{ $tournament->location_city }}@endif
                </div>
            </td>
            <td class="stamp">
                Scheda incontri<br>
                <strong>Pag. {{ $chunkIndex + 1 }}/{{ $pageCount }}</strong>
            </td>
        </tr>
    </table>

    @if($chunk->isEmpty())
        <div class="empty-state">Nessun incontro registrato</div>
    @else
        <table class="legend">
            <tr>
                <td style="width: 6%;">N.</td>
                <td style="width: 34%; padding-left: 11px;">Angolo rosso</td>
                <td style="width: 24%; text-align: center;">Categoria</td>
                <td style="width: 34%; text-align: right; padding-right: 11px;">Angolo blu</td>
            </tr>
        </table>

        <table class="bouts">
            @foreach($chunk as $matchRecord)
                <tr>
                    <td class="no">{{ $matchRecord->sort }}</td>
                    <td class="corner corner-red">
                        <div class="athlete">{{ $matchRecord->redCorner?->full_name ?? '—' }}</div>
                        <div class="team">{{ $matchRecord->red_corner_team }}</div>
                    </td>
                    <td class="spine">
                        <div class="class">{{ $matchRecord->discipline->label }}</div>
                        <div class="format">
                            {{ $matchRecord->weightCategory->label }}
                            &middot; {{ $matchRecord->rounds }}&times;{{ $matchRecord->minutes_per_round }}
                            @if($matchRecord->scheduled_time)
                                &middot; {{ $matchRecord->scheduled_time }}
                            @endif
                        </div>
                    </td>
                    <td class="corner corner-blue">
                        <div class="athlete">{{ $matchRecord->blueCorner?->full_name ?? '—' }}</div>
                        <div class="team">{{ $matchRecord->blue_corner_team }}</div>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">Generato il {{ now()->format('d/m/Y H:i') }}</div>
@endforeach

</body>
</html>
