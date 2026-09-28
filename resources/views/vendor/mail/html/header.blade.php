@props(['url' => null])
@php($logoUrl = \App\Support\AppLogo::emailUrl())
<tr>
<td class="px header" align="center">
@if ($logoUrl)
<img src="{{ $logoUrl }}" width="140" alt="{{ config('app.name') }}" class="logo">
@else
<p class="wordmark">{!! $slot !!}</p>
@endif
</td>
</tr>
