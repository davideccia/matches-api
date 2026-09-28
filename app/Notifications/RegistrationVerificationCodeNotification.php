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

    public function __construct(public readonly string $code)
    {
        $this->locale(app()->getLocale());
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = __('emails.verification_code.subject');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.registration.verification-code', [
                'code' => $this->code,
                'ttlMinutes' => RegistrationVerificationCode::TTL_MINUTES,
            ]);
    }
}
