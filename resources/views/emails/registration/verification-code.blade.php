<x-mail::message
    :preheader="__('emails.verification_code.preheader', ['minutes' => $ttlMinutes])"
    :reason="__('emails.verification_code.reason', ['app' => config('app.name')])"
>
# {{ __('emails.verification_code.heading') }}

{{ __('emails.verification_code.intro') }}

<x-mail::code :label="__('emails.verification_code.code_label')">{{ $code }}</x-mail::code>

<x-mail::subcopy>
{{ __('emails.verification_code.expiry', ['minutes' => $ttlMinutes]) }}

{{ __('emails.verification_code.ignore') }}
</x-mail::subcopy>

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
