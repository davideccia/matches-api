@props(['preheader' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ config('app.name') }}</title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<style>table,td,div,h1,h2,p{font-family:Arial,sans-serif;}</style>
<![endif]-->
<style>
:root { color-scheme: light dark; supported-color-schemes: light dark; }
body { margin:0; padding:0; background-color:#f5f5f5; }
@media (max-width:620px) {
.container { width:100% !important; }
.px { padding-left:20px !important; padding-right:20px !important; }
}
@media (prefers-color-scheme: dark) {
body, .wrapper { background-color:#0f0f0f !important; }
.card { background-color:#171717 !important; border-color:#2e2e2e !important; }
.content-cell h1, .content-cell p, .content-cell li, .content-cell code, .value, .table td { color:#f5f5f5 !important; }
.content-cell h2, .content-cell h3 { color:#dddddd !important; }
.subcopy p { color:#bbbbbb !important; }
.panel-content .label, .content-cell .button-fallback, .table th, .footer p, .footer a { color:#999999 !important; }
.content-cell a, .wordmark { color:#38bdf8 !important; }
.content-cell a.button { color:#0b1a24 !important; }
.button-cell-primary { background-color:#0ea5e9 !important; }
.button-cell-success { background-color:#4ade80 !important; }
.button-cell-error { background-color:#f87171 !important; }
.panel-content { background-color:#262626 !important; border-color:#2e2e2e !important; }
.footer, .table th { border-color:#2e2e2e !important; }
.badge-success { background-color:#16301f !important; color:#4ade80 !important; }
.badge-warning { background-color:#33290f !important; color:#fbbf24 !important; }
.badge-error { background-color:#361b1b !important; color:#f87171 !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>
@if ($preheader)
<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">{{ $preheader }}&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;&#8199;&#847;</div>
@endif
<table class="wrapper" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f5f5f5">
<tr>
<td align="center" style="padding:32px 12px;">
<!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table class="container card" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff">
{!! $header ?? '' !!}
<tr>
<td class="px content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
{!! $subcopy ?? '' !!}
</td>
</tr>
{!! $footer ?? '' !!}
</table>
<!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
