<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ __('pdf.registration.title') }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }

        html {
            -webkit-print-color-adjust: exact;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #201e1d;
            background-color: #ffffff;
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
            padding: 44pt 48pt;
        }

        /* ── TITLE ── */
        .title {
            font-size: 34pt;
            font-weight: bold;
            line-height: 1.08;
        }

        .subtitle {
            font-size: 10pt;
            color: #5f5c5a;
            margin-top: 10pt;
            width: 340pt;
        }

        .rule-strong {
            height: 2pt;
            font-size: 0;
            line-height: 0;
            background-color: #201e1d;
            margin-top: 26pt;
        }

        /* ── DETAILS ── */
        .detail-label,
        .detail-label-last {
            width: 34%;
            padding: 16pt 0 14pt;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 1.4pt;
            text-transform: uppercase;
            color: #8a8785;
        }

        .detail-value,
        .detail-value-last {
            padding: 16pt 0 14pt;
            font-size: 15pt;
            font-weight: bold;
        }

        .detail-label,
        .detail-value {
            border-bottom: 1pt solid #d8d6d5;
        }

        .detail-label-last,
        .detail-value-last {
            border-bottom: 2pt solid #201e1d;
        }

        /* ── CREDENTIAL ── */
        .credential {
            margin-top: 34pt;
            background-color: #0084d1;
            padding: 24pt 28pt;
            color: #ffffff;
        }

        .credential-label {
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 2.4pt;
            text-transform: uppercase;
        }

        .credential-code {
            font-family: "Courier New", Courier, monospace;
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 0.5pt;
            padding-top: 12pt;
            word-wrap: break-word;
        }

        .credential-note {
            font-size: 8pt;
            padding-top: 12pt;
            width: 300pt;
        }

        /* ── FOOTER ── */
        .footer {
            position: fixed;
            left: 48pt;
            bottom: 44pt;
            width: 499pt;
            border-top: 2pt solid #201e1d;
        }

        .footer-left,
        .footer-right {
            padding: 10pt 0 0;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 1.2pt;
            text-transform: uppercase;
            color: #8a8785;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }
    </style>
</head>
<body>

<div class="page">

    {{-- Title --}}
    <div class="title">{{ __('pdf.registration.title') }}</div>
    <div class="subtitle">{{ __('pdf.registration.subtitle') }}</div>

    <div class="rule-strong"></div>

    {{-- Details --}}
    <table>
        <tr>
            <td class="detail-label">{{ __('pdf.registration.label_athlete') }}</td>
            <td class="detail-value">{{ $registration->athlete->full_name }}</td>
        </tr>
        <tr>
            <td class="detail-label">{{ __('pdf.registration.label_tournament') }}</td>
            <td class="detail-value">{{ $registration->tournament->name }}</td>
        </tr>
        <tr>
            <td class="detail-label">{{ __('pdf.registration.label_discipline') }}</td>
            <td class="detail-value">{{ $registration->discipline->label }}</td>
        </tr>
        <tr>
            <td class="detail-label-last">{{ __('pdf.registration.label_category') }}</td>
            <td class="detail-value-last">{{ $registration->weightCategory->label }}</td>
        </tr>
    </table>

    {{-- Credential --}}
    <div class="credential">
        <div class="credential-label">{{ __('pdf.registration.credential_label') }}</div>
        <div class="credential-code">{{ $registration->id }}</div>
        <div class="credential-note">{{ __('pdf.registration.credential_note') }}</div>
    </div>

</div>

{{-- Footer --}}
<table class="footer">
    <tr>
        <td class="footer-left">{{ __('pdf.registration.title') }}</td>
        <td class="footer-right">
            {{ __('pdf.registration.exported_at', ['date' => now()->format('d/m/Y H:i')]) }}
        </td>
    </tr>
</table>

</body>
</html>
