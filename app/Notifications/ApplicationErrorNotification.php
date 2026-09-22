<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

class ApplicationErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public readonly string $exceptionClass;

    public readonly string $exceptionMessage;

    public readonly string $file;

    public readonly int $line;

    public function __construct(Throwable $exception)
    {
        $this->exceptionClass = $exception::class;
        $this->exceptionMessage = $exception->getMessage();
        $this->file = $exception->getFile();
        $this->line = $exception->getLine();
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
        return (new MailMessage)
            ->subject('['.config('app.name').'] Errore applicativo: '.$this->exceptionClass)
            ->markdown('emails.errors.application-error', [
                'exceptionClass' => $this->exceptionClass,
                'exceptionMessage' => $this->exceptionMessage,
                'file' => $this->file,
                'line' => $this->line,
            ]);
    }
}
