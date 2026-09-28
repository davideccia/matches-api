@props(['rows', 'monospace' => false])
@foreach ($rows as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
