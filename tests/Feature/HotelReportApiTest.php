<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HotelReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculates_occupancy_adr_and_revpar_from_imported_data(): void
    {
        $this->artisan('import:xml')->assertSuccessful();
        $hotel = Hotel::query()->where('external_code', '1')->first();
        Sanctum::actingAs(User::factory()->manager($hotel)->create());

        // Dezembro/2022 no Hotel Foco Prime: reserva 1 (3 diárias de 100) + reserva 2 (2 diárias de 250).
        // 2 quartos * 31 dias = 62 room-nights; 5 vendidas; receita 800.
        $this->getJson(self::API."/hotels/{$hotel->id}/report?from=2022-12-01&to=2022-12-31")
            ->assertOk()
            ->assertJsonPath('data.period.days', 31)
            ->assertJsonPath('data.occupancy.available_room_nights', 62)
            ->assertJsonPath('data.occupancy.occupied_room_nights', 5)
            ->assertJsonPath('data.occupancy.rate_percent', 8.06)
            ->assertJsonPath('data.revenue.room_revenue', 800.0)
            ->assertJsonPath('data.revenue.adr', 160.0)
            ->assertJsonPath('data.revenue.revpar', 12.9)
            ->assertJsonPath('data.reserves.by_status.partially_paid', 1)
            ->assertJsonPath('data.reserves.by_status.pending', 1)
            ->assertJsonPath('data.reserves.booked_amount', 800.0)
            ->assertJsonPath('data.reserves.paid_amount', 100.0)
            ->assertJsonPath('data.reserves.outstanding_amount', 700.0)
            ->assertJsonPath('data.top_rooms.0.nights', 3);
    }

    public function test_cancelled_reserves_do_not_count_as_revenue(): void
    {
        $room = Room::factory()->create(['inventory' => 1]);
        $reserve = Reserve::factory()->forRoom($room)->between('2030-01-10', '2030-01-12')->create();
        $reserve->dailies()->createMany([
            ['date' => '2030-01-10', 'value' => 100],
            ['date' => '2030-01-11', 'value' => 100],
        ]);
        $reserve->payments()->create(['method' => PaymentMethod::Pix, 'value' => 50]);
        $cancelled = Reserve::factory()->forRoom($room)->cancelled()->between('2030-01-15', '2030-01-16')->create();
        $cancelled->dailies()->create(['date' => '2030-01-15', 'value' => 999]);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(self::API."/hotels/{$room->hotel_id}/report?from=2030-01-01&to=2030-01-31")
            ->assertOk()
            ->assertJsonPath('data.occupancy.occupied_room_nights', 2)
            ->assertJsonPath('data.revenue.room_revenue', 200.0)
            ->assertJsonPath('data.reserves.by_status.cancelled', 1)
            ->assertJsonPath('data.reserves.paid_amount', 50.0);
    }

    public function test_only_admin_or_hotel_manager_can_see_report(): void
    {
        $hotel = Hotel::factory()->create();

        Sanctum::actingAs(User::factory()->receptionist($hotel)->create());
        $this->getJson(self::API."/hotels/{$hotel->id}/report")->assertForbidden();

        Sanctum::actingAs(User::factory()->manager(Hotel::factory()->create())->create());
        $this->getJson(self::API."/hotels/{$hotel->id}/report")->assertForbidden();
    }

    public function test_requires_authentication(): void
    {
        $hotel = Hotel::factory()->create();

        $this->getJson(self::API."/hotels/{$hotel->id}/report")->assertUnauthorized();
    }
}
