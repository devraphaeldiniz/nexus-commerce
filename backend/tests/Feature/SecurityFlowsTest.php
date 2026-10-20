<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityFlowsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutating_authenticated_route_requires_csrf_token(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\AuthenticateApi::class);
        $this->withCookie('nexus_auth', 'token')
            ->withHeader('X-CSRF-TOKEN', 'missing-cookie')
            ->postJson('/api/profile/2fa/setup')
            ->assertStatus(419);
    }

    public function test_otp_rejects_invalid_code_and_counts_attempt(): void
    {
        $user = User::factory()->create([
            'email_verification_code' => '123456',
            'email_verification_expires_at' => now()->addMinutes(10),
        ]);
        $this->postJson('/api/auth/verify-email', ['email' => $user->email, 'code' => '000000'])
            ->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'otp_attempts' => 1]);
    }

    public function test_otp_locks_after_five_attempts(): void
    {
        $user = User::factory()->create([
            'email_verification_code' => '123456',
            'email_verification_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 5,
        ]);
        $this->postJson('/api/auth/verify-email', ['email' => $user->email, 'code' => '000000'])
            ->assertStatus(429);
    }
}
