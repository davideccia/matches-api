<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ApplicationErrorNotification;
use App\Notifications\RegistrationVerificationCodeNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserCredentialsNotification;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EmailNotificationsTest extends TestCase
{
    /**
     * @return array<string, array{Closure(): Notification, string}>
     */
    public static function notifications(): array
    {
        return [
            'reset password' => [fn () => new ResetPasswordNotification('https://app.test/reset-password?token=abc'), 'https://app.test/reset-password?token=abc'],
            'user credentials' => [fn () => new UserCredentialsNotification('mario.rossi@example.com', 'mario.rossi'), 'mario.rossi@example.com'],
            'verification code' => [fn () => new RegistrationVerificationCodeNotification('482913'), '482913'],
            'application error' => [fn () => new ApplicationErrorNotification(new RuntimeException('boom')), 'RuntimeException'],
        ];
    }

    private function send(Notification $notification): Email
    {
        $user = (new User)->forceFill(['username' => 'mario.rossi', 'email' => 'mario.rossi@example.com']);
        $user->notifyNow($notification);

        return app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    #[DataProvider('notifications')]
    public function test_email_renders_translated_html_with_its_data(Closure $makeNotification, string $expectedContent): void
    {
        foreach (['it', 'en'] as $locale) {
            app()->setLocale($locale);

            $email = $this->send($makeNotification());
            $html = $email->getHtmlBody();

            $this->assertStringNotContainsString('emails.', $email->getSubject());
            $this->assertStringNotContainsString('emails.', $html);
            $this->assertStringContainsString('<html lang="'.$locale.'"', $html);
            $this->assertStringContainsString($expectedContent, $html);
        }
    }

    public function test_email_keeps_the_locale_active_when_it_was_dispatched(): void
    {
        app()->setLocale('it');
        $notification = new RegistrationVerificationCodeNotification('482913');

        app()->setLocale('en');
        $email = $this->send($notification);

        $this->assertSame('Codice di verifica iscrizione', $email->getSubject());
        $this->assertStringContainsString('<html lang="it"', $email->getHtmlBody());
    }

    public function test_email_header_shows_the_configured_png_logo(): void
    {
        Storage::disk('public')->putFileAs('settings', UploadedFile::fake()->image('logo.png'), 'logo.png');

        $html = $this->send(new RegistrationVerificationCodeNotification('482913'))->getHtmlBody();

        $this->assertStringContainsString('<img src="'.url('api/public/settings/logo').'?v=', $html);
    }

    public function test_email_header_falls_back_to_the_app_name_for_a_non_email_safe_logo(): void
    {
        Storage::disk('public')->put('settings/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $html = $this->send(new RegistrationVerificationCodeNotification('482913'))->getHtmlBody();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString(config('app.name'), $html);
    }

    public function test_package_mail_built_from_mail_message_lines_uses_the_app_layout(): void
    {
        app()->setLocale('it');
        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(object $notifiable): array
            {
                return ['mail'];
            }

            public function toMail(object $notifiable): MailMessage
            {
                return (new MailMessage)->subject('Backup')->line('Backup completato')->action('Apri', 'https://app.test/backups');
            }
        };

        $html = $this->send($notification)->getHtmlBody();

        $this->assertStringContainsString('bgcolor="#ffffff"', $html);
        $this->assertStringContainsString('@media (prefers-color-scheme: dark)', $html);
        $this->assertStringContainsString('https://app.test/backups', $html);
        $this->assertStringContainsString(__('emails.common.automatic'), $html);
    }
}
