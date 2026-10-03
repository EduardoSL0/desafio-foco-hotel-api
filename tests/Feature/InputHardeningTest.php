<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regressões encontradas no teste bruto (fuzzing): tipos inválidos não podem gerar
 * erro 500 nem ser aceitos silenciosamente.
 */
class InputHardeningTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidDates(): array
    {
        return ['lista' => [['2030-01-01']], 'objeto' => [['a' => 1]], 'decimal' => [1.5], 'inteiro' => [20300101], 'data impossível' => ['2030-02-30']];
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_on_quote_and_reserve_return_422(mixed $value): void
    {
        $room = Room::factory()->create();
        $base = ['room_id' => $room->id, 'check_in' => now()->addDays(5)->toDateString(), 'check_out' => now()->addDays(7)->toDateString(),
            'guests' => [['name' => 'A', 'last_name' => 'B', 'phone' => '5571999990000']]];

        foreach (['check_in', 'check_out'] as $field) {
            $this->postJson(self::API.'/reserves/quote', [...$base, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->postJson(self::API.'/reserves', [...$base, $field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    #[DataProvider('invalidDates')]
    public function test_invalid_dates_on_coupons_and_promotions_return_422(mixed $value): void
    {
        $hotel = Hotel::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/coupons', ['code' => 'X1', 'type' => 'percent', 'value' => 5, 'valid_from' => $value, 'valid_until' => '2030-01-10'])
            ->assertUnprocessable()->assertJsonValidationErrors('valid_from');

        $this->postJson(self::API.'/promotions', ['hotel_id' => $hotel->id, 'name' => 'P', 'discount_percent' => 5, 'starts_at' => $value, 'ends_at' => '2030-01-10'])
            ->assertUnprocessable()->assertJsonValidationErrors('starts_at');
    }

    public function test_array_dates_in_query_strings_return_422(): void
    {
        $room = Room::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(self::API.'/availability?check_in[]=x&check_out='.now()->addDays(3)->toDateString())->assertUnprocessable();
        $this->getJson(self::API."/rooms/{$room->id}/availability?check_in[]=x&check_out=2030-01-02")->assertUnprocessable();
        $this->getJson(self::API."/hotels/{$room->hotel_id}/report?from[]=x&to=2030-01-02")->assertUnprocessable();
    }

    public function test_login_with_array_email_returns_422(): void
    {
        $this->postJson(self::API.'/auth/login', ['email' => ['a@b.com'], 'password' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_integers_must_be_real_integers(): void
    {
        $hotel = Hotel::factory()->create();
        $reserve = Reserve::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson(self::API.'/rooms', ['hotel_id' => $hotel->id, 'name' => 'Q', 'capacity' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('capacity');

        foreach ([1.5, true, '1'] as $method) {
            $this->postJson(self::API."/reserves/{$reserve->id}/payments", ['method' => $method, 'value' => 10])
                ->assertUnprocessable()->assertJsonValidationErrors('method');
        }
    }

    public function test_non_numeric_route_ids_are_not_found(): void
    {
        Room::factory()->create();

        foreach (["1'", '1 OR 1=1', 'abc', '-1', '1.0'] as $id) {
            $this->getJson(self::API.'/rooms/'.rawurlencode($id))->assertNotFound();
        }
    }
}
