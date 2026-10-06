<?php

namespace App\Services;

use App\Models\AccountRecoveryOtp;
use App\Models\User;
use App\Notifications\AccountRecoveryOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generación y verificación del OTP de recuperación de cuenta enviado por correo.
 * El código nunca se guarda en texto plano: solo su hash.
 */
class AccountRecoveryService
{
    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const RECOVERY_GRANT_TTL_MINUTES = 10;

    /**
     * Genera un OTP numérico aleatorio (CSPRNG), invalida el anterior y lo envía por correo.
     * Si el correo no se entrega, el OTP recién creado se invalida y devuelve false.
     */
    public function sendOtp(User $user): bool
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $codeHash = Hash::make($code);

        AccountRecoveryOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => $codeHash,
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0,
                'consumed_at' => null,
            ]
        );

        try {
            $user->notify(new AccountRecoveryOtpNotification($code, self::OTP_TTL_MINUTES));
        } catch (Throwable $exception) {
            // Solo se invalida si sigue siendo el OTP de esta solicitud: una solicitud posterior no se toca.
            AccountRecoveryOtp::where('user_id', $user->id)
                ->where('code_hash', $codeHash)
                ->update(['consumed_at' => now()]);

            // Solo tipo y mensaje técnico: nunca el código, el cuerpo del correo ni el correo del destinatario.
            Log::error('No se pudo enviar el OTP de recuperación de cuenta.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }

        return true;
    }

    public function verifyOtp(User $user, string $code): bool
    {
        $otp = AccountRecoveryOtp::where('user_id', $user->id)->first();

        if (! $otp || $otp->isConsumed() || $otp->isExpired() || $otp->attempts >= self::OTP_MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        return true;
    }

    /**
     * Establece la nueva contraseña e invalida las sesiones anteriores del usuario.
     */
    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
        $user->endAllSessions();
    }
}
