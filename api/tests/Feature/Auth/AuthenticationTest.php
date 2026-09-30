<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_always_creates_a_customer_even_if_a_role_is_submitted(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Mallory',
            'email' => 'Mallory@Example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'role' => 'admin',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.role', Role::CUSTOMER->value)
            ->assertJsonPath('user.email', 'mallory@example.com')
            ->assertJsonStructure(['token']);

        $this->assertSame(Role::CUSTOMER, User::firstWhere('email', 'mallory@example.com')->role);
    }

    public function test_user_can_log_in_and_use_the_token_on_me(): void
    {
        $user = User::factory()->agent()->create(['email' => 'agent@test.io', 'password' => 'Secret123']);

        $token = $this->postJson('/api/v1/auth/login', ['email' => 'agent@test.io', 'password' => 'Secret123'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'agent');
    }

    public function test_invalid_credentials_return_the_consistent_validation_error_format(): void
    {
        User::factory()->create(['email' => 'user@test.io']);

        $this->postJson('/api/v1/auth/login', ['email' => 'user@test.io', 'password' => 'wrong-password'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['message', 'code', 'errors' => ['email']]);
    }

    public function test_protected_endpoints_return_401_json_without_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
    }

    public function test_login_is_rate_limited(): void
    {
        $limit = config('servicedesk.rate_limits.auth');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'victim@test.io', 'password' => 'guess'.$i])
                ->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'victim@test.io', 'password' => 'another-guess'])
            ->assertTooManyRequests()
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['password' => 'Secret123']);
        $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Secret123'])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_password_reset_flow_resets_password_and_revokes_existing_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@test.io']);
        $user->createToken('old-session');

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'reset@test.io'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use (&$token, $user) {
            $token = $notification->token;

            return str_starts_with(
                $notification->toMail($user)->actionUrl,
                config('servicedesk.frontend_url').'/reset-password?token=',
            );
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@test.io',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => 'reset@test.io', 'password' => 'NewSecret123'])->assertOk();
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@test.io'])
            ->assertOk()
            ->assertJsonPath('message', 'If that email is registered, a password reset link has been sent.');
    }
}
