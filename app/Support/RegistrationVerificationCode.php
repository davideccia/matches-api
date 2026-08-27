<?php

namespace App\Support;

use App\Models\Athlete;
use App\Notifications\RegistrationVerificationCodeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

class RegistrationVerificationCode
{
    private const int MAX_VERIFY_ATTEMPTS = 5;

    private const int MAX_REQUESTS = 3;

    private const int REQUEST_WINDOW_SECONDS = 900;

    public const int TTL_MINUTES = 10;

    private static function cacheKey(string $normalizedTaxNumber): string
    {
        return 'registration_code:'.hash('sha256', $normalizedTaxNumber);
    }

    public static function issue(string $taxNumber, string $submittedEmail): void
    {
        $taxNumber = Athlete::normalizeTaxNumber($taxNumber);

        $limiterKey = 'registration_code_requests:'.hash('sha256', $taxNumber);

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_REQUESTS)) {
            return;
        }

        RateLimiter::hit($limiterKey, self::REQUEST_WINDOW_SECONDS);

        $athlete = Athlete::where('tax_number', $taxNumber)->first();

        // An existing athlete is always contacted at the address on file, never
        // at the one supplied by the caller. Without this, anyone knowing a tax
        // number could have the code delivered to their own mailbox.
        $recipient = $athlete?->email ?? Athlete::normalizeEmail($submittedEmail);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::cacheKey($taxNumber), [
            'code_hash' => hash('sha256', $code),
            'expires_at' => $expiresAt->timestamp,
            'attempts' => 0,
        ], $expiresAt);

        Notification::route('mail', $recipient)->notify(new RegistrationVerificationCodeNotification($code));
    }

    public static function verify(string $taxNumber, string $code): bool
    {
        $key = self::cacheKey(Athlete::normalizeTaxNumber($taxNumber));

        $entry = Cache::get($key);

        if (! is_array($entry)) {
            return false;
        }

        if (! hash_equals($entry['code_hash'], hash('sha256', $code))) {

            $entry['attempts']++;

            if ($entry['attempts'] >= self::MAX_VERIFY_ATTEMPTS) {

                Cache::forget($key);

            } else {

                // Re-store against the original deadline: a wrong guess must
                // never buy the attacker a fresh TTL.
                Cache::put($key, $entry, Carbon::createFromTimestamp($entry['expires_at']));
            }

            return false;
        }

        Cache::forget($key);

        return true;
    }
}
