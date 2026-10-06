<?php

namespace Tests\Feature\Auth;

use App\Models\AccountRecoveryFile;
use App\Models\User;
use App\Services\AccountRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AccountRecoveryFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_generate_a_recovery_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/seguridad/recuperacion/archivo');

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertDatabaseCount('account_recovery_files', 1);

        $content = $this->streamedContent($response);
        $this->assertStringContainsString('AquaSmart', $content);
    }

    public function test_recovery_file_token_is_not_predictable_and_is_hashed(): void
    {
        $user = User::factory()->create();

        $tokenA = app(AccountRecoveryService::class)->generateRecoveryFile($user);
        $tokenB = app(AccountRecoveryService::class)->generateRecoveryFile(User::factory()->create());
        $record = AccountRecoveryFile::where('user_id', $user->id)->first();

        $this->assertNotEquals($tokenA, $record->token_hash);
        $this->assertNotEquals($tokenA, $tokenB);
        $this->assertEquals(hash('sha256', $tokenA), $record->token_hash);
    }

    public function test_valid_recovery_file_grants_access_to_set_a_new_password(): void
    {
        $user = User::factory()->create();
        $token = app(AccountRecoveryService::class)->generateRecoveryFile($user);

        $file = UploadedFile::fake()->createWithContent('aquasmart-recuperacion.txt', $token);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file])
            ->assertRedirect('/recuperar-cuenta/nueva-contrasena');
    }

    public function test_used_recovery_file_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $token = app(AccountRecoveryService::class)->generateRecoveryFile($user);

        $file = UploadedFile::fake()->createWithContent('aquasmart-recuperacion.txt', $token);
        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file]);

        $fileAgain = UploadedFile::fake()->createWithContent('aquasmart-recuperacion.txt', $token);
        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $fileAgain])
            ->assertSessionHasErrors(['recovery_file' => __('auth.recovery_invalid')]);
    }

    public function test_invalid_recovery_file_is_rejected(): void
    {
        $user = User::factory()->create();
        app(AccountRecoveryService::class)->generateRecoveryFile($user);

        $file = UploadedFile::fake()->createWithContent('falso.txt', 'contenido-inventado');

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file])
            ->assertSessionHasErrors(['recovery_file' => __('auth.recovery_invalid')]);
    }

    public function test_recovery_file_verification_is_rate_limited(): void
    {
        $user = User::factory()->create();
        app(AccountRecoveryService::class)->generateRecoveryFile($user);

        foreach (range(1, 5) as $attempt) {
            $file = UploadedFile::fake()->createWithContent('falso.txt', 'contenido-inventado');
            $this->withSession(['recovery.email' => $user->email])
                ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file]);
        }

        $file = UploadedFile::fake()->createWithContent('falso.txt', 'contenido-inventado');
        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file])
            ->assertSessionHasErrors('recovery_file');
    }

    public function test_generating_a_new_file_invalidates_the_previous_one(): void
    {
        $user = User::factory()->create();
        $service = app(AccountRecoveryService::class);

        $oldToken = $service->generateRecoveryFile($user);
        $service->generateRecoveryFile($user);

        $file = UploadedFile::fake()->createWithContent('aquasmart-recuperacion.txt', $oldToken);

        $this->withSession(['recovery.email' => $user->email])
            ->post('/recuperar-cuenta/verificar/archivo', ['recovery_file' => $file])
            ->assertSessionHasErrors(['recovery_file' => __('auth.recovery_invalid')]);

        $this->assertDatabaseCount('account_recovery_files', 1);
    }

    private function streamedContent($response): string
    {
        ob_start();
        $response->baseResponse->sendContent();

        return ob_get_clean();
    }
}
