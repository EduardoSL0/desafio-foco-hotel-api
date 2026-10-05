<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Pré-reservas online sem pagamento não podem bloquear o inventário para sempre. */
class PendingReserveExpirationTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_booking_gets_a_payment_deadline(): void
    {
        $this->freezeTime();
        $room = Room::factory()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.expires_at', now()->addHours(24)->toIso8601String());
    }

    public function test_expired_pre_reservation_frees_the_room_and_is_cancelled_by_the_scheduler(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        Coupon::factory()->create(['code' => 'UMUSO', 'max_uses' => 1]);

        $this->postJson(self::API.'/reserves', $this->payload($room, ['coupon_code' => 'UMUSO']))->assertCreated();
        $this->postJson(self::API.'/reserves', $this->payload($room))->assertStatus(409);

        $this->travel(25)->hours();

        // Já não ocupa o quarto, mesmo antes do comando rodar.
        $this->postJson(self::API.'/reserves/quote', $this->payload($room))->assertJsonPath('data.available_units', 1);

        $this->artisan('reserves:expire')->assertSuccessful();

        $this->assertSame('cancelled', Reserve::query()->first()->status->value);
        $this->assertSame(0, Coupon::query()->where('code', 'UMUSO')->value('used_count'));
        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated();
    }

    public function test_payment_guarantees_the_reservation(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        $id = $this->postJson(self::API.'/reserves', $this->payload($room))->json('data.id');

        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());
        $this->postJson(self::API."/reserves/{$id}/payments", ['method' => 3, 'value' => 10])->assertCreated();

        $this->travel(48)->hours();
        $this->artisan('reserves:expire')->assertSuccessful();

        $reserve = Reserve::query()->find($id);
        $this->assertSame('partially_paid', $reserve->status->value);
        $this->assertNull($reserve->expires_at);
    }

    public function test_booking_made_by_hotel_staff_does_not_expire(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->receptionist($room->hotel)->create());

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated()->assertJsonPath('data.expires_at', null);
    }

    public function test_expiration_can_be_disabled(): void
    {
        config(['hotel.reservations.pending_ttl_hours' => 0]);
        $room = Room::factory()->create();

        $this->postJson(self::API.'/reserves', $this->payload($room))->assertCreated()->assertJsonPath('data.expires_at', null);
    }

    private function payload(Room $room, array $overrides = []): array
    {
        return [
            'room_id' => $room->id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(12)->toDateString(),
            'guests' => [['name' => 'Ana', 'last_name' => 'Souza', 'phone' => '5571999990001']],
            ...$overrides,
        ];
    }
}
