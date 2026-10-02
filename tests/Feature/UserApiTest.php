<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_manager_for_a_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/users', [
            'name' => 'Nova Gerente',
            'email' => 'nova@foco.test',
            'password' => 'senhaForte123',
            'role' => 'manager',
            'hotel_id' => $hotel->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.hotel_id', $hotel->id)
            ->assertJsonMissingPath('data.password');

        $this->postJson(self::API.'/auth/login', ['email' => 'nova@foco.test', 'password' => 'senhaForte123'])->assertOk();
    }

    public function test_manager_creates_receptionist_in_own_hotel_without_informing_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        $this->postJson(self::API.'/users', [
            'name' => 'Recepção',
            'email' => 'rec@foco.test',
            'password' => 'senhaForte123',
            'role' => 'receptionist',
        ])
            ->assertCreated()
            ->assertJsonPath('data.hotel_id', $hotel->id);
    }

    public function test_manager_cannot_create_admin_or_staff_for_another_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $other = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        $this->postJson(self::API.'/users', [
            'name' => 'X', 'email' => 'x@foco.test', 'password' => 'senhaForte123', 'role' => 'admin',
        ])->assertForbidden();

        $this->postJson(self::API.'/users', [
            'name' => 'Y', 'email' => 'y@foco.test', 'password' => 'senhaForte123', 'role' => 'receptionist', 'hotel_id' => $other->id,
        ])->assertForbidden();
    }

    public function test_receptionist_cannot_manage_users(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($hotel)->create());

        $this->getJson(self::API.'/users')->assertForbidden();
        $this->postJson(self::API.'/users', [
            'name' => 'Z', 'email' => 'z@foco.test', 'password' => 'senhaForte123', 'role' => 'receptionist',
        ])->assertForbidden();
    }

    public function test_validates_password_strength_and_hotel_requirement(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/users', [
            'name' => 'Fraca', 'email' => 'fraca@foco.test', 'password' => '123', 'role' => 'receptionist',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'hotel_id']);
    }

    public function test_manager_lists_only_own_hotel_staff(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager($hotel)->create();
        User::factory()->receptionist($hotel)->create();
        User::factory()->receptionist(Hotel::factory()->create())->create();
        User::factory()->admin()->create();
        Sanctum::actingAs($manager);

        $this->getJson(self::API.'/users')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_password_change_revokes_existing_tokens(): void
    {
        $hotel = Hotel::factory()->create();
        $receptionist = User::factory()->receptionist($hotel)->create();
        $receptionist->createToken('api');
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        $this->patchJson(self::API."/users/{$receptionist->id}", ['password' => 'novaSenha123'])->assertOk();

        $this->assertSame(0, $receptionist->tokens()->count());
    }

    public function test_manager_cannot_promote_staff_to_admin_or_move_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $receptionist = User::factory()->receptionist($hotel)->create();
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        $this->patchJson(self::API."/users/{$receptionist->id}", ['role' => 'admin'])->assertForbidden();
        $this->patchJson(self::API."/users/{$receptionist->id}", ['hotel_id' => Hotel::factory()->create()->id])->assertForbidden();
    }

    public function test_cannot_delete_self_but_can_delete_staff(): void
    {
        $hotel = Hotel::factory()->create();
        $manager = User::factory()->manager($hotel)->create();
        $receptionist = User::factory()->receptionist($hotel)->create();
        Sanctum::actingAs($manager);

        $this->deleteJson(self::API."/users/{$manager->id}")->assertForbidden();
        $this->deleteJson(self::API."/users/{$receptionist->id}")->assertNoContent();

        $this->assertModelMissing($receptionist);
    }

    public function test_user_can_view_own_profile_but_not_others(): void
    {
        $hotel = Hotel::factory()->create();
        $receptionist = User::factory()->receptionist($hotel)->create();
        $other = User::factory()->receptionist($hotel)->create();
        Sanctum::actingAs($receptionist);

        $this->getJson(self::API."/users/{$receptionist->id}")->assertOk();
        $this->getJson(self::API."/users/{$other->id}")->assertForbidden();
    }
}
