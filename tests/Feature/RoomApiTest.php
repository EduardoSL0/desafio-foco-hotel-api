<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_rooms_publicly_with_pagination(): void
    {
        Room::factory()->count(3)->create();

        $this->getJson(self::API.'/rooms')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'hotel_id', 'hotel', 'name', 'capacity', 'inventory', 'daily_price']],
                'links',
                'meta' => ['current_page', 'total', 'per_page'],
            ]);
    }

    public function test_filters_rooms_by_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        Room::factory()->count(2)->for($hotel)->create();
        Room::factory()->create();

        $this->getJson(self::API."/rooms?hotel_id={$hotel->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_shows_a_room(): void
    {
        $room = Room::factory()->create(['name' => 'Suíte Master']);

        $this->getJson(self::API."/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Suíte Master')
            ->assertJsonPath('data.daily_price', 100.0);
    }

    public function test_returns_404_for_unknown_room(): void
    {
        $this->getJson(self::API.'/rooms/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Recurso não encontrado.');
    }

    public function test_guest_cannot_create_room(): void
    {
        $hotel = Hotel::factory()->create();

        $this->postJson(self::API.'/rooms', ['hotel_id' => $hotel->id, 'name' => 'Quarto'])
            ->assertUnauthorized();
    }

    public function test_manager_creates_room_in_own_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        $this->postJson(self::API.'/rooms', [
            'hotel_id' => $hotel->id,
            'name' => 'Standard Casal',
            'description' => 'Cama de casal e varanda',
            'capacity' => 2,
            'inventory' => 10,
            'daily_price' => 189.90,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Standard Casal')
            ->assertJsonPath('data.inventory', 10)
            ->assertJsonPath('data.daily_price', 189.9);

        $this->assertDatabaseHas('rooms', ['hotel_id' => $hotel->id, 'name' => 'Standard Casal', 'inventory' => 10]);
    }

    public function test_admin_creates_room_in_any_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/rooms', ['hotel_id' => $hotel->id, 'name' => 'Luxo'])
            ->assertCreated()
            ->assertJsonPath('data.capacity', 2)
            ->assertJsonPath('data.inventory', 1);
    }

    public function test_manager_cannot_create_room_in_another_hotel(): void
    {
        $own = Hotel::factory()->create();
        $other = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->manager($own)->create());

        $this->postJson(self::API.'/rooms', ['hotel_id' => $other->id, 'name' => 'Quarto'])
            ->assertForbidden();
    }

    public function test_receptionist_cannot_create_room(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($hotel)->create());

        $this->postJson(self::API.'/rooms', ['hotel_id' => $hotel->id, 'name' => 'Quarto'])
            ->assertForbidden();
    }

    public function test_validates_room_payload(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/rooms', ['capacity' => 0, 'daily_price' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['hotel_id', 'name', 'capacity', 'daily_price']);
    }

    public function test_updates_room(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->putJson(self::API."/rooms/{$room->id}", ['name' => 'Novo nome', 'daily_price' => 250])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo nome')
            ->assertJsonPath('data.daily_price', 250.0);
    }

    public function test_cannot_move_room_to_another_hotel(): void
    {
        $room = Room::factory()->create();
        $other = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson(self::API."/rooms/{$room->id}", ['hotel_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hotel_id');
    }

    public function test_full_put_with_same_hotel_updates_room(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson(self::API."/rooms/{$room->id}", [
            'hotel_id' => $room->hotel_id,
            'name' => 'Standard Casal',
            'capacity' => 2,
            'inventory' => $room->inventory,
            'daily_price' => 150,
        ])->assertOk()->assertJsonPath('data.daily_price', 150.0);

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'hotel_id' => $room->hotel_id, 'daily_price' => 150]);
    }

    public function test_deletes_room_softly(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->deleteJson(self::API."/rooms/{$room->id}")->assertNoContent();

        $this->assertSoftDeleted($room);
        $this->getJson(self::API."/rooms/{$room->id}")->assertNotFound();
    }

    public function test_cannot_delete_room_with_upcoming_reserves(): void
    {
        $room = Room::factory()->create();
        Reserve::factory()->forRoom($room)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson(self::API."/rooms/{$room->id}")->assertStatus(409);

        $this->assertNotSoftDeleted($room);
    }

    public function test_reports_availability_considering_inventory(): void
    {
        $room = Room::factory()->create(['inventory' => 3]);
        Reserve::factory()->forRoom($room)->between(now()->addDays(10)->toDateString(), now()->addDays(12)->toDateString())->create();

        $this->getJson(self::API."/rooms/{$room->id}/availability?".http_build_query([
            'check_in' => now()->addDays(11)->toDateString(),
            'check_out' => now()->addDays(14)->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('data.inventory', 3)
            ->assertJsonPath('data.available_units', 2);
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Room::factory()->create(['name' => 'Suíte Master']);
        Room::factory()->create(['name' => 'Quarto 100% reformado']);

        $this->getJson(self::API.'/rooms?search=%')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Quarto 100% reformado');
        $this->getJson(self::API.'/rooms?search=_')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_capacity_cannot_go_below_guests_of_future_reserves(): void
    {
        $room = Room::factory()->create(['capacity' => 3]);
        $this->postJson(self::API.'/reserves', [
            'room_id' => $room->id,
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(6)->toDateString(),
            'guests' => [
                ['name' => 'A', 'last_name' => 'Souza', 'phone' => '5571999990001'],
                ['name' => 'B', 'last_name' => 'Souza', 'phone' => '5571999990002'],
                ['name' => 'C', 'last_name' => 'Souza', 'phone' => '5571999990003'],
            ],
        ])->assertCreated();
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->patchJson(self::API."/rooms/{$room->id}", ['capacity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('capacity');
        $this->patchJson(self::API."/rooms/{$room->id}", ['capacity' => 4])->assertOk();
    }
}
