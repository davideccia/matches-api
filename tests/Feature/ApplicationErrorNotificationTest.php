<?php

namespace Tests\Feature;

use App\Notifications\ApplicationErrorNotification;
use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationErrorNotificationTest extends TestCase
{
    private function report(Exception $exception): void
    {
        app(ExceptionHandler::class)->report($exception);
    }

    public function test_reportable_exception_sends_notification_when_address_configured(): void
    {
        config(['mail.admin_error_address' => 'admin@example.com']);
        Notification::fake();

        $this->report(new Exception('boom'));

        Notification::assertSentOnDemand(ApplicationErrorNotification::class);
    }

    public function test_no_notification_sent_when_address_not_configured(): void
    {
        config(['mail.admin_error_address' => null]);
        Notification::fake();

        $this->report(new Exception('boom'));

        Notification::assertNothingSent();
    }

    public function test_repeated_identical_exception_is_throttled(): void
    {
        config(['mail.admin_error_address' => 'admin@example.com']);
        Notification::fake();

        $exception = new Exception('boom');

        $this->report($exception);
        $this->report($exception);

        Notification::assertSentOnDemandTimes(ApplicationErrorNotification::class, 1);
    }

    public function test_non_reportable_exception_does_not_send_notification(): void
    {
        config(['mail.admin_error_address' => 'admin@example.com']);
        Notification::fake();

        $this->report(ValidationException::withMessages(['field' => 'invalid']));

        Notification::assertNothingSent();
    }
}
