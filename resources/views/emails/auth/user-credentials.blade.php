<x-mail::message
    :preheader="__('emails.user_credentials.preheader', ['app' => config('app.name')])"
    :reason="__('emails.user_credentials.reason', ['app' => config('app.name')])"
>
# {{ __('emails.common.greeting', ['name' => $username]) }}

{{ __('emails.user_credentials.intro', ['app' => config('app.name')]) }}

<x-mail::info-box :rows="[
    __('emails.user_credentials.username_label') => $username,
    __('emails.user_credentials.email_label') => $email,
]" />

<x-mail::subcopy>
{{ __('emails.user_credentials.security') }}
</x-mail::subcopy>

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
