<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestAccountRecoveryRequest extends FormRequest
{
    private const MAX_ATTEMPTS = 3;

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
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * Limita cuántas solicitudes de código puede hacer un mismo correo/IP en una ventana de tiempo.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.recovery_throttle', [
                'seconds' => RateLimiter::availableIn($this->throttleKey()),
            ]),
        ]);
    }

    public function hit(): void
    {
        RateLimiter::hit($this->throttleKey(), self::DECAY_SECONDS);
    }

    private function throttleKey(): string
    {
        return 'recovery-send|'.Str::transliterate(Str::lower($this->string('email'))).'|'.$this->ip();
    }
}
