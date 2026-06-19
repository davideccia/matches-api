<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Scheda Iscrizione</title>
    <style>
        @page {
            size: A4;
            margin: 0 0 15mm 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1E293B;
            line-height: 1.55;
            background: #FFFFFF;
        }

        /* ── HEADER ─────────────────────────── */
        .header {
            background-color: #0F1F3D;
            padding: 22px 28px 18px;
        }

        .header-eyebrow {
            font-size: 8px;
            letter-spacing: 3.5px;
            text-transform: uppercase;
            color: #C8A84B;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .header-tournament {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 22px;
            color: #FFFFFF;
            font-weight: bold;
            line-height: 1.2;
            margin-bottom: 8px;
        }

        .header-meta {
            font-size: 10px;
            color: #94A3B8;
        }

        .header-sep {
            color: #C8A84B;
            padding: 0 7px;
        }

        /* ── GOLD ACCENT BAR ─────────────────── */
        .gold-bar {
            height: 3px;
            background-color: #C8A84B;
        }

        /* ── PAGE BODY ───────────────────────── */
        .body {
            padding: 22px 28px;
        }

        /* ── ATHLETE HERO ────────────────────── */
        .athlete-hero {
            background-color: #F5F7FA;
            border-left: 5px solid #C8A84B;
            padding: 14px 18px;
            margin-bottom: 22px;
        }

        .athlete-name {
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 24px;
            font-weight: bold;
            color: #0F1F3D;
            line-height: 1.15;
            margin-bottom: 3px;
        }

        .athlete-tax {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            color: #64748B;
            letter-spacing: 0.8px;
            margin-bottom: 10px;
        }

        .athlete-meta-row {
            width: 100%;
            border-collapse: collapse;
        }

        .athlete-meta-row td {
            vertical-align: top;
            padding-right: 20px;
        }

        .athlete-meta-row td:last-child {
            padding-right: 0;
        }

        .meta-kicker {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94A3B8;
            display: block;
            margin-bottom: 2px;
        }

        .meta-value {
            font-size: 11px;
            color: #1E293B;
            font-weight: bold;
        }

        /* ── SECTION ─────────────────────────── */
        .section {
            margin-bottom: 20px;
        }

        .section-heading {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .section-label {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            color: #C8A84B;
            white-space: nowrap;
            padding-right: 10px;
            vertical-align: middle;
        }

        .section-rule-cell {
            border-top: 1px solid #E2E8F0;
            vertical-align: middle;
        }

        /* ── DATA GRID ───────────────────────── */
        .data-grid {
            width: 100%;
            border-collapse: collapse;
        }

        .data-grid tr:nth-child(even) td {
            background-color: #F8FAFC;
        }

        .dg-label {
            width: 36%;
            font-size: 9.5px;
            color: #64748B;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }

        .dg-value {
            font-size: 11px;
            color: #1E293B;
            padding: 5px 0;
            vertical-align: top;
        }

        /* ── TWO-COLUMN ──────────────────────── */
        .two-col {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .col-left {
            width: 50%;
            padding-right: 16px;
            vertical-align: top;
        }

        .col-right {
            width: 50%;
            padding-left: 16px;
            border-left: 1px solid #E2E8F0;
            vertical-align: top;
        }

        /* ── FOOTER ──────────────────────────── */
        .footer {
            width: 100%;
            border-collapse: collapse;
            border-top: 1px solid #E2E8F0;
            margin-top: 26px;
            padding-top: 9px;
        }

        .footer td {
            vertical-align: top;
            font-size: 8px;
            color: #94A3B8;
            padding-top: 9px;
        }

        .footer-right {
            text-align: right;
        }

        .footer-id {
            font-family: 'Courier New', Courier, monospace;
            color: #CBD5E1;
        }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="header-eyebrow">Scheda Iscrizione</div>
    <div class="header-tournament">{{ $registration->tournament->name }}</div>
    <div class="header-meta">
        {{ $registration->tournament->date?->format('d/m/Y') }}
        <span class="header-sep">·</span>
        {{ $registration->tournament->location_city }}
    </div>
</div>
<div class="gold-bar"></div>

<div class="body">

    {{-- ATHLETE HERO --}}
    <div class="athlete-hero">
        <div class="athlete-name">{{ $registration->athlete->full_name }}</div>
        <div class="athlete-tax">CF: {{ $registration->athlete->tax_number }}</div>
        <table class="athlete-meta-row">
            <tr>
                <td>
                    <span class="meta-kicker">Data di nascita</span>
                    <span class="meta-value">{{ $registration->athlete->birth_date?->format('d/m/Y') ?? '—' }}</span>
                </td>
                <td>
                    <span class="meta-kicker">Genere</span>
                    <span class="meta-value">{{ $registration->athlete->gender?->label() ?? '—' }}</span>
                </td>
                <td>
                    <span class="meta-kicker">Team</span>
                    <span class="meta-value">{{ $registration->athlete->team_name ?? '—' }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- TORNEO --}}
    <div class="section">
        <table class="section-heading">
            <tr>
                <td class="section-label">Torneo</td>
                <td class="section-rule-cell"></td>
            </tr>
        </table>
        <table class="data-grid">
            <tr>
                <td class="dg-label">Sede</td>
                <td class="dg-value">{{ $registration->tournament->location_name }}</td>
            </tr>
            <tr>
                <td class="dg-label">Indirizzo</td>
                <td class="dg-value">{{ $registration->tournament->location_address }}</td>
            </tr>
            <tr>
                <td class="dg-label">Città</td>
                <td class="dg-value">{{ $registration->tournament->location_city }}</td>
            </tr>
        </table>
    </div>

    {{-- DISCIPLINA + CATEGORIA (two-column) --}}
    <table class="two-col">
        <tr>
            <td class="col-left">
                <table class="section-heading">
                    <tr>
                        <td class="section-label">Disciplina</td>
                        <td class="section-rule-cell"></td>
                    </tr>
                </table>
                <table class="data-grid">
                    <tr>
                        <td class="dg-label">Nome</td>
                        <td class="dg-value">{{ $registration->discipline->label }}</td>
                    </tr>
                    @if($registration->discipline->rounds !== null && $registration->discipline->minutes_per_round !== null)
                        <tr>
                            <td class="dg-label">Struttura</td>
                            <td class="dg-value">{{ $registration->discipline->rounds }}
                                × {{ $registration->discipline->minutes_per_round }} min
                            </td>
                        </tr>
                    @endif
                </table>
            </td>
            <td class="col-right">
                <table class="section-heading">
                    <tr>
                        <td class="section-label">Categoria di peso</td>
                        <td class="section-rule-cell"></td>
                    </tr>
                </table>
                <table class="data-grid">
                    <tr>
                        <td class="dg-label">Categoria</td>
                        <td class="dg-value">{{ $registration->weightCategory->label }}</td>
                    </tr>
                    <tr>
                        <td class="dg-label">Limite</td>
                        <td class="dg-value">{{ $registration->weightCategory->value }} kg</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ISCRIZIONE --}}
    <div class="section">
        <table class="section-heading">
            <tr>
                <td class="section-label">Iscrizione</td>
                <td class="section-rule-cell"></td>
            </tr>
        </table>

        <table class="data-grid">
            @if($registration->notes)
                <tr>
                    <td class="dg-label">Note</td>
                    <td class="dg-value">{{ $registration->notes }}</td>
                </tr>
            @endif
            <tr>
                <td class="dg-label">Iscritto il</td>
                <td class="dg-value">{{ $registration->created_at->format('d/m/Y \a\l\l\e H:i') }}</td>
            </tr>
        </table>
    </div>

    {{-- FOOTER --}}
    <table class="footer">
        <tr>
            <td>Generata il {{ now()->format('d/m/Y \a\l\l\e H:i') }}</td>
            <td class="footer-right">
                <span class="footer-id">{{ $registration->id }}</span>
            </td>
        </tr>
    </table>

</div>

</body>
</html>
