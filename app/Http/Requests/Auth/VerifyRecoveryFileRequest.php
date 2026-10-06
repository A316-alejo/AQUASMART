<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyRecoveryFileRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'recovery_file' => ['required', 'file', 'max:10'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(string $email): void
    {
        $key = $this->throttleKey($email);

        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'recovery_file' => __('auth.recovery_throttle', [
                'seconds' => RateLimiter::availableIn($key),
            ]),
        ]);
    }

    public function hit(string $email): void
    {
        RateLimiter::hit($this->throttleKey($email), self::DECAY_SECONDS);
    }

    private function throttleKey(string $email): string
    {
        return 'recovery-file|'.Str::transliterate(Str::lower($email)).'|'.$this->ip();
    }
}
