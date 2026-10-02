<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromotionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_creates_promotion_that_affects_quotes(): void
    {
        $room = Room::factory()->create(['daily_price' => 200]);
        Sanctum::actingAs(User::factory()->manager($room->hotel)->create());

        $this->postJson(self::API.'/promotions', [
            'hotel_id' => $room->hotel_id,
            'room_id' => $room->id,
            'name' => 'Feriado',
            'discount_percent' => 25,
            'starts_at' => now()->addDays(10)->toDateString(),
            'ends_at' => now()->addDays(20)->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.discount_percent', 25.0)
            ->assertJsonPath('data.active', true);

        $this->postJson(self::API.'/reserves/quote', [
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
        ])
            ->assertOk()
            ->assertJsonPath('data.total', 300.0)
            ->assertJsonPath('data.adjustments.0.label', 'Promoção: Feriado');
    }

    public function test_room_must_belong_to_promotion_hotel(): void
    {
        $hotel = Hotel::factory()->create();
        $foreignRoom = Room::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/promotions', [
            'hotel_id' => $hotel->id,
            'room_id' => $foreignRoom->id,
            'name' => 'Errada',
            'discount_percent' => 10,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_validates_period_and_percent(): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/promotions', [
            'hotel_id' => $hotel->id,
            'name' => 'Inválida',
            'discount_percent' => 150,
            'starts_at' => now()->addDays(5)->toDateString(),
            'ends_at' => now()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_percent', 'ends_at']);
    }

    public function test_manager_cannot_manage_promotions_of_another_hotel(): void
    {
        $promotion = Promotion::factory()->create();
        Sanctum::actingAs(User::factory()->manager(Hotel::factory()->create())->create());

        $this->patchJson(self::API."/promotions/{$promotion->id}", ['active' => false])->assertForbidden();
        $this->deleteJson(self::API."/promotions/{$promotion->id}")->assertForbidden();
        $this->getJson(self::API.'/promotions')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_updates_and_deletes_promotion(): void
    {
        $promotion = Promotion::factory()->create();
        Sanctum::actingAs(User::factory()->manager($promotion->hotel)->create());

        $this->patchJson(self::API."/promotions/{$promotion->id}", ['active' => false, 'discount_percent' => 30])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.discount_percent', 30.0);

        $this->deleteJson(self::API."/promotions/{$promotion->id}")->assertNoContent();
        $this->assertModelMissing($promotion);
    }
}
