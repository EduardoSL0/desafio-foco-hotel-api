<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_with_hidden_password(): void
    {
        $this->artisan('user:create-admin', ['email' => 'dono@hotel.example', '--name' => 'Dono'])
            ->expectsQuestion('Senha (mín. 8 caracteres, com letras e números)', 'SenhaForte2026')
            ->expectsQuestion('Confirme a senha', 'SenhaForte2026')
            ->assertSuccessful();

        $user = User::query()->where('email', 'dono@hotel.example')->firstOrFail();
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertNull($user->hotel_id);
        $this->assertTrue(Hash::check('SenhaForte2026', $user->password));
    }

    public function test_rejects_weak_or_mismatched_password(): void
    {
        $this->artisan('user:create-admin', ['email' => 'dono@hotel.example'])
            ->expectsQuestion('Senha (mín. 8 caracteres, com letras e números)', '123')
            ->expectsQuestion('Confirme a senha', '456')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
