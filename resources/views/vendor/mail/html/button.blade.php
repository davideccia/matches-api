@props([
    'url',
    'color' => 'primary',
    'align' => 'left',
])
@php($fill = match ($color) { 'success' => '#15803d', 'error' => '#b91c1c', default => '#0369a1' })
<table class="action" role="presentation" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td align="{{ $align }}">
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
<tr>
<td class="button-cell button-cell-{{ $color }}" align="center" bgcolor="{{ $fill }}" style="background-color:{{ $fill }};">
<!--[if mso]>
<v:roundrect href="{{ $url }}" style="height:44px;v-text-anchor:middle;width:220px;" arcsize="18%" stroke="f" fillcolor="{{ $fill }}">
<w:anchorlock/><center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">{!! $slot !!}</center>
</v:roundrect>
<![endif]-->
<!--[if !mso]><!-->
<a href="{{ $url }}" class="button" target="_blank" rel="noopener">{!! $slot !!}</a>
<!--<![endif]-->
</td>
</tr>
</table>
</td>
</tr>
</table>
<p class="button-fallback">{{ __('emails.common.button_fallback') }}<br><a href="{{ $url }}">{{ $url }}</a></p>
