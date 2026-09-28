<x-mail::message
    :preheader="$exceptionClass"
    :reason="__('emails.application_error.reason', ['app' => config('app.name')])"
>
# {{ __('emails.application_error.heading') }}

<span class="badge badge-error">{{ __('emails.application_error.badge') }}</span>

<x-mail::info-box monospace :rows="[
    __('emails.application_error.exception_label') => $exceptionClass,
    __('emails.application_error.message_label') => $exceptionMessage,
    __('emails.application_error.file_label') => $file.':'.$line,
]" />

<x-mail::subcopy>
{{ __('emails.application_error.hint') }}
</x-mail::subcopy>
</x-mail::message>
