<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyRecoveryCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'regex:/^[0-9]{4}-[0-9]{4}$/'],
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
            'code' => __('auth.recovery_throttle', [
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
        return 'recovery-code|'.Str::transliterate(Str::lower($email)).'|'.$this->ip();
    }
}
