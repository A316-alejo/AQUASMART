<?php

namespace App\Services;

use App\Models\AccountRecoveryCode;
use App\Models\AccountRecoveryFile;
use App\Models\AccountRecoveryOtp;
use App\Models\User;
use App\Notifications\AccountRecoveryOtpNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Generación y verificación de los tres mecanismos de recuperación de cuenta:
 * OTP por correo, recovery codes y archivo de recuperación. Ninguno de los
 * secretos en texto plano se persiste; solo se devuelven una vez al generarlos.
 */
class AccountRecoveryService
{
    public const OTP_TTL_MINUTES = 10;

    public const OTP_MAX_ATTEMPTS = 5;

    public const RECOVERY_CODES_COUNT = 8;

    public const RECOVERY_GRANT_TTL_MINUTES = 10;

    /**
     * Genera un OTP numérico aleatorio (CSPRNG), invalida el anterior y lo envía por correo.
     */
    public function sendOtp(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        AccountRecoveryOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
                'attempts' => 0,
                'consumed_at' => null,
            ]
        );

        $user->notify(new AccountRecoveryOtpNotification($code, self::OTP_TTL_MINUTES));
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
     * Genera un set nuevo de recovery codes (formato NNNN-NNNN) y descarta el anterior.
     * Devuelve los códigos en texto plano: es la única vez que están disponibles.
     *
     * @return list<string>
     */
    public function generateRecoveryCodes(User $user): array
    {
        AccountRecoveryCode::where('user_id', $user->id)->delete();

        $plainCodes = [];
        $rows = [];
        $now = now();

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $code = sprintf('%04d-%04d', random_int(0, 9999), random_int(0, 9999));
            $plainCodes[] = $code;
            $rows[] = [
                'user_id' => $user->id,
                'code_hash' => Hash::make($code),
                'used_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        AccountRecoveryCode::insert($rows);

        return $plainCodes;
    }

    public function verifyRecoveryCode(User $user, string $code): bool
    {
        $code = trim($code);

        $candidates = AccountRecoveryCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->get(['id', 'code_hash']);

        foreach ($candidates as $candidate) {
            if (Hash::check($code, $candidate->code_hash)) {
                $candidate->forceFill(['used_at' => now()])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Genera un token nuevo para el archivo de recuperación y descarta el anterior.
     * Devuelve el token en texto plano: es la única vez que está disponible.
     */
    public function generateRecoveryFile(User $user): string
    {
        $token = Str::random(48);

        AccountRecoveryFile::updateOrCreate(
            ['user_id' => $user->id],
            ['token_hash' => hash('sha256', $token), 'used_at' => null]
        );

        return $token;
    }

    public function verifyRecoveryFile(User $user, string $token): bool
    {
        $record = AccountRecoveryFile::where('user_id', $user->id)
            ->where('token_hash', hash('sha256', trim($token)))
            ->whereNull('used_at')
            ->first();

        if (! $record) {
            return false;
        }

        $record->forceFill(['used_at' => now()])->save();

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
