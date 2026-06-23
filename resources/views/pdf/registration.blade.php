<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Conferma di Iscrizione</title>
    <style type="text/css">
        @page {
            size: A4;
            margin: 20pt;
        }

        html {
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #2d3a4e;
            margin: 0;
            padding: 0;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        td {
            padding: 0;
            vertical-align: top;
        }

        /* ── PAGE ── */
        .page {
            padding: 20pt;
        }

        /* ── HEADER WRAP ── */
        .header-wrap {
            border-radius: 10pt;
            overflow: hidden;
            border: 1pt solid #dce4ed;
        }

        /* ── HEADER ── */
        .header-table {
            width: 100%;
        }

        .header-accent {
            width: 7pt;
            background-color: #5b7fa8;
        }

        .header-content {
            padding: 26pt 32pt 26pt 22pt;
        }

        .header-event {
            font-size: 8pt;
            color: #5b7fa8;
            text-transform: uppercase;
            letter-spacing: 1.5pt;
            font-weight: bold;
        }

        .header-title {
            font-size: 20pt;
            font-weight: bold;
            color: #2d3a4e;
            margin-top: 5pt;
        }

        .header-subtitle {
            font-size: 8.5pt;
            color: #8fa0b5;
            margin-top: 4pt;
            letter-spacing: 0.3pt;
        }

        /* ── BODY ── */
        .body-wrap {
            padding: 28pt 0pt;
        }

        /* ── DETAIL WRAP ── */
        .detail-wrap {
            border-radius: 8pt;
            overflow: hidden;
            border: 1pt solid #dce4ed;
        }

        /* ── DETAIL TABLE ── */
        .detail-table {
            width: 100%;
        }

        .detail-label {
            width: 34%;
            border-bottom: 1pt solid #dce4ed;
            border-right: 1pt solid #dce4ed;
            padding: 10pt 14pt;
            font-size: 8pt;
            font-weight: bold;
            color: #8fa0b5;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
        }

        .detail-value {
            border-bottom: 1pt solid #dce4ed;
            padding: 10pt 14pt;
            font-size: 11pt;
            color: #2d3a4e;
            font-weight: bold;
        }

        .detail-label-last {
            width: 34%;
            border-right: 1pt solid #dce4ed;
            padding: 10pt 14pt;
            font-size: 8pt;
            font-weight: bold;
            color: #8fa0b5;
            text-transform: uppercase;
            letter-spacing: 0.8pt;
        }

        .detail-value-last {
            padding: 10pt 14pt;
            font-size: 11pt;
            font-weight: bold;
            color: #2d3a4e;
        }

        /* ── DIVIDER ── */
        .divider-table {
            width: 100%;
            margin-top: 26pt;
            margin-bottom: 26pt;
        }

        .divider-line {
            border-top: 1pt solid #dce4ed;
            height: 0;
            font-size: 0;
            line-height: 0;
        }

        .divider-center {
            text-align: center;
            padding-top: 0;
        }

        .divider-dot {
            font-size: 6pt;
            color: #d4a96a;
            letter-spacing: 4pt;
        }

        /* ── CREDENTIAL BLOCK ── */
        .credential-outer {
            width: 100%;
        }

        .credential-cell {
            padding: 0;
            text-align: center;
        }

        .credential-box {
            border: 1.5pt solid #dce4ed;
            border-left: 4pt solid #d4a96a;
            border-radius: 8pt;
            padding: 18pt 28pt;
            text-align: center;
        }

        .credential-label {
            font-size: 7pt;
            font-weight: bold;
            color: #d4a96a;
            text-transform: uppercase;
            letter-spacing: 2.5pt;
        }

        .credential-rule {
            border-top: 1pt solid #dce4ed;
            margin-top: 10pt;
            margin-bottom: 10pt;
            height: 0;
            font-size: 0;
            line-height: 0;
        }

        .credential-code {
            font-family: "Courier New", Courier, monospace;
            font-size: 12pt;
            font-weight: bold;
            color: #2d3a4e;
            letter-spacing: 1pt;
        }

        .credential-note {
            font-size: 7pt;
            color: #8fa0b5;
            margin-top: 10pt;
            letter-spacing: 0.3pt;
        }

        /* ── FOOTER ── */
        .footer-table {
            width: 100%;
            border-top: 1pt solid #dce4ed;
        }

        .footer-right {
            padding: 9pt 0pt;
            font-size: 7pt;
            color: #8fa0b5;
            text-align: right;
            letter-spacing: 0.3pt;
        }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header-wrap">
        <table class="header-table">
            <tr>
                <td class="header-accent"></td>
                <td class="header-content">
                    <div class="header-event">{{ $registration->tournament->name }}</div>
                    <div class="header-title">Conferma di Iscrizione</div>
                    <div class="header-subtitle">Documento ufficiale di partecipazione</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Body --}}
    <div class="body-wrap">

        <div class="detail-wrap">
            <table class="detail-table">
                <tr>
                    <td class="detail-label">Atleta</td>
                    <td class="detail-value">{{ $registration->athlete->full_name }}</td>
                </tr>
                <tr>
                    <td class="detail-label">Torneo</td>
                    <td class="detail-value">{{ $registration->tournament->name }}</td>
                </tr>
                <tr>
                    <td class="detail-label">Disciplina</td>
                    <td class="detail-value">{{ $registration->discipline->label }}</td>
                </tr>
                <tr>
                    <td class="detail-label-last">Categoria</td>
                    <td class="detail-value-last">{{ $registration->weightCategory->label }}</td>
                </tr>
            </table>
        </div>

        {{-- Divider --}}
        <table class="divider-table">
            <tr>
                <td class="divider-line"></td>
            </tr>
        </table>

        {{-- Credential block --}}
        <table class="credential-outer">
            <tr>
                <td class="credential-cell">
                    <div class="credential-box">
                        <div class="credential-label">Codice Iscrizione</div>
                        <div class="credential-rule"></div>
                        <div class="credential-code">{{ $registration->id }}</div>
                        <div class="credential-note">Conserva questo documento per certificare la tua iscrizione</div>
                    </div>
                </td>
            </tr>
        </table>

    </div>

    {{-- Footer --}}
    <table class="footer-table">
        <tr>
            <td class="footer-right">
                Esportato il {{ now()->format('d/m/Y H:i') }}
            </td>
        </tr>
    </table>

</div>
</body>
</html>
