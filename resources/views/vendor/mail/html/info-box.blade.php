@props(['rows', 'monospace' => false])
<table class="panel" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="panel-content" bgcolor="#fafafa">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
@foreach ($rows as $label => $value)
<tr>
<td class="label"@if (! $loop->first) style="padding-top:12px;"@endif>{{ $label }}</td>
</tr>
<tr>
<td class="value{{ $monospace ? ' monospace' : '' }}">{{ $value }}</td>
</tr>
@endforeach
</table>
</td>
</tr>
</table>
