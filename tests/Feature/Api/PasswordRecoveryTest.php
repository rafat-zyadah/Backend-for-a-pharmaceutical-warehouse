<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Mail\SupervisorPasswordRecoveryMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InteractsWithApiUsers;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use InteractsWithApiUsers;
    use RefreshDatabase;

    public function test_forgot_password_returns_supervisor_phone_on_mobile(): void
    {
        $supervisor = $this->supervisorUser();

        $this->postJson('/api/v1/auth/forgot-password', [], [
            'X-Client-Platform' => 'mobile',
        ])
            ->assertOk()
            ->assertJsonPath('supervisor.phone', $supervisor->phone)
            ->assertJsonPath('supervisor.name', $supervisor->name)
            ->assertJsonFragment([
                'message' => 'للاستعلام عن كلمة المرور أو إعادة تعيينها، يرجى التواصل مع المشرف مباشرةً.',
            ]);
    }

    public function test_forgot_password_returns_supervisor_phone_on_desktop(): void
    {
        $supervisor = $this->supervisorUser();

        $this->postJson('/api/v1/auth/forgot-password', [], [
            'X-Client-Platform' => 'desktop',
        ])
            ->assertOk()
            ->assertJsonPath('supervisor.phone', $supervisor->phone);
    }

    public function test_forgot_password_is_not_available_on_web(): void
    {
        $this->supervisorUser();

        $this->postJson('/api/v1/auth/forgot-password', [], [
            'X-Client-Platform' => 'web',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_supervisor_can_recover_password_via_email_and_whatsapp(): void
    {
        Mail::fake();

        $supervisor = $this->supervisorUser();

        $this->postJson('/api/v1/auth/supervisor/recover-password', [
            'login' => 'supervisor',
        ], [
            'X-Client-Platform' => 'web',
        ])
            ->assertOk()
            ->assertJsonPath('channels', ['email', 'whatsapp']);

        Mail::assertSent(SupervisorPasswordRecoveryMail::class, function (SupervisorPasswordRecoveryMail $mail) use ($supervisor): bool {
            return $mail->hasTo($supervisor->email)
                && $mail->password === 'password';
        });

        $this->assertDatabaseHas('state_transition_logs', [
            'entity_id' => $supervisor->id,
            'event' => 'recover_supervisor_password',
        ]);
    }

    public function test_supervisor_recovery_requires_web_platform(): void
    {
        $this->supervisorUser();

        $this->postJson('/api/v1/auth/supervisor/recover-password', [
            'login' => 'supervisor',
        ], [
            'X-Client-Platform' => 'mobile',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_supervisor_recovery_rejects_unknown_account(): void
    {
        $this->supervisorUser();

        $this->postJson('/api/v1/auth/supervisor/recover-password', [
            'login' => 'unknown-user',
        ], [
            'X-Client-Platform' => 'web',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }
}
