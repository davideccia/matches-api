<x-mail::message
    :preheader="__('emails.reset_password.preheader', ['app' => config('app.name')])"
    :reason="__('emails.reset_password.reason', ['email' => $user->email])"
>
# {{ __('emails.common.greeting', ['name' => $user->username]) }}

{{ __('emails.reset_password.intro') }}

<x-mail::button :url="$url">
{{ __('emails.reset_password.action') }}
</x-mail::button>

<x-mail::subcopy>
{{ __('emails.reset_password.expiry', ['minutes' => $expiryMinutes]) }}

{{ __('emails.reset_password.ignore') }}
</x-mail::subcopy>

{{ __('emails.common.thanks') }}<br>
{{ config('app.name') }}
</x-mail::message>
