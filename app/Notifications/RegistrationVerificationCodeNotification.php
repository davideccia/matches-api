<?php

namespace App\Notifications;

use App\Support\RegistrationVerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegistrationVerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $code) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Codice di verifica iscrizione')
            ->markdown('emails.registration.verification-code', [
                'code' => $this->code,
                'ttlMinutes' => RegistrationVerificationCode::TTL_MINUTES,
            ]);
    }
}
