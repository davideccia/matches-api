<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCredentialsNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $email,
        public readonly string $username,
    ) {
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
        $subject = __('emails.user_credentials.subject');

        return (new MailMessage)
            ->subject($subject)
            ->markdown('emails.auth.user-credentials', [
                'email' => $this->email,
                'username' => $this->username,
            ]);
    }
}
