<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AccountRecoveryCode;
use App\Models\AccountRecoveryFile;
use App\Services\AccountRecoveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Autoservicio (usuario autenticado) para generar sus propios recovery codes y archivo de
 * recuperación, que luego podrá usar en el flujo público de recuperación de cuenta.
 */
class AccountRecoverySetupController extends Controller
{
    public function show(Request $request): View
    {
        $userId = $request->user()->id;

        return view('auth.recovery.setup', [
            'unusedCodesCount' => AccountRecoveryCode::where('user_id', $userId)->whereNull('used_at')->count(),
            'hasRecoveryFile' => AccountRecoveryFile::where('user_id', $userId)->whereNull('used_at')->exists(),
        ]);
    }

    public function generateCodes(Request $request, AccountRecoveryService $service): RedirectResponse
    {
        $codes = $service->generateRecoveryCodes($request->user());

        return redirect()->route('security.recovery.show')
            ->with('status', __('auth.recovery_codes_generated'))
            ->with('generated_codes', $codes);
    }

    public function generateFile(Request $request, AccountRecoveryService $service): StreamedResponse
    {
        $token = $service->generateRecoveryFile($request->user());

        $content = "AquaSmart - Archivo de recuperacion de cuenta\r\n".
            "No comparta este archivo: quien lo tenga puede recuperar el acceso a su cuenta.\r\n".
            "Utilícelo en la pantalla de recuperación de cuenta, opción \"Usar archivo de recuperación\".\r\n\r\n".
            $token."\r\n";

        return response()->streamDownload(
            function () use ($content) {
                echo $content;
            },
            'aquasmart-recuperacion.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }
}
