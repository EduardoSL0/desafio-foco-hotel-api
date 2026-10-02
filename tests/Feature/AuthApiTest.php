<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_in_and_uses_bearer_token(): void
    {
        $user = User::factory()->admin()->create(['email' => 'admin@foco.test', 'password' => 'segredo123']);

        $token = $this->postJson(self::API.'/auth/login', ['email' => 'admin@foco.test', 'password' => 'segredo123'])
            ->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.role', 'admin')
            ->json('access_token');

        $this->assertNotEmpty($token);

        $this->withToken($token)
            ->getJson(self::API.'/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'user@foco.test', 'password' => 'correta']);

        $this->postJson(self::API.'/auth/login', ['email' => 'user@foco.test', 'password' => 'errada'])
            ->assertUnauthorized()
            ->assertJsonMissingPath('access_token');
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson(self::API.'/auth/me')->assertUnauthorized();
        $this->getJson(self::API.'/reserves')->assertUnauthorized();
    }
}
