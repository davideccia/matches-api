<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $tournament->name }} — Dettagliato</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #1E293B; }

        .header { background: #0F1F3D; color: #fff; padding: 12px 16px; margin-bottom: 14px; }
        .header h1 { font-size: 15px; margin-bottom: 4px; }
        .header .meta { font-size: 9px; color: #C8A84B; }

        table { width: 100%; border-collapse: collapse; }
        th { background: #0F1F3D; color: #C8A84B; text-align: left; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        td { padding: 5px 6px; border-bottom: 1px solid #E2E8F0; vertical-align: top; }
        tr:nth-child(even) td { background: #F8FAFC; }

        .status { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 8px; text-transform: uppercase; }
        .winner { font-weight: bold; color: #C8A84B; }
        .points { font-size: 8px; color: #64748B; }
        .footer { margin-top: 12px; font-size: 8px; color: #94A3B8; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $tournament->name }} — Dettagliato</h1>
        <div class="meta">
            {{ $tournament->date?->format('d/m/Y') }}
            @if($tournament->location_name) — {{ $tournament->location_name }}@endif
            @if($tournament->location_city) ({{ $tournament->location_city }})@endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:25px">#</th>
                <th style="width:50px">Orario</th>
                <th>Angolo Rosso</th>
                <th>Angolo Blu</th>
                <th>Disciplina</th>
                <th>Categoria</th>
                <th style="width:45px">Round</th>
                <th>Stato</th>
                <th>Vincitore</th>
                <th>Metodo</th>
                <th>Punti Giudici</th>
            </tr>
        </thead>
        <tbody>
            @forelse($tournament->matchRecords as $match)
                <tr>
                    <td>{{ $match->sort }}</td>
                    <td>{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '—' }}</td>
                    <td>{{ $match->redCorner?->full_name ?? '—' }}</td>
                    <td>{{ $match->blueCorner?->full_name ?? '—' }}</td>
                    <td>{{ $match->discipline?->name ?? '—' }}</td>
                    <td>{{ $match->weightCategory?->name ?? '—' }}</td>
                    <td>
                        @if($match->rounds && $match->minutes_per_round)
                            {{ $match->rounds }}×{{ $match->minutes_per_round }}'
                        @else
                            —
                        @endif
                    </td>
                    <td><span class="status">{{ $match->status?->value ?? '—' }}</span></td>
                    <td>
                        @if($match->winner)
                            <span class="winner">{{ $match->winner->full_name }}</span>
                            @if($match->end_round) (R{{ $match->end_round }})@endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $match->end_method?->value ?? '—' }}</td>
                    <td>
                        @if(!empty($match->judges_points))
                            <span class="points">{{ implode(' / ', $match->judges_points) }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align:center; padding: 16px; color:#94A3B8;">Nessun match registrato</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
