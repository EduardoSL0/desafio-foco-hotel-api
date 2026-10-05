<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Hotel;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AvailabilitySearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_available_rooms_sorted_by_price_with_totals(): void
    {
        $hotel = Hotel::factory()->create();
        $expensive = Room::factory()->for($hotel)->create(['name' => 'Luxo', 'daily_price' => 300, 'capacity' => 2]);
        $cheap = Room::factory()->for($hotel)->create(['name' => 'Standard', 'daily_price' => 100, 'capacity' => 2]);
        $full = Room::factory()->for($hotel)->create(['daily_price' => 50, 'inventory' => 1]);
        Room::factory()->for($hotel)->create(['daily_price' => 80, 'capacity' => 1]); // capacidade insuficiente
        Room::factory()->for($hotel)->withoutRate()->create();                       // sem tarifa
        Reserve::factory()->forRoom($full)->between($this->day(10), $this->day(12))->create();

        $this->getJson(self::API.'/availability?'.http_build_query([
            'check_in' => $this->day(10),
            'check_out' => $this->day(12),
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('meta.results', 2)
            ->assertJsonPath('data.0.room.id', $cheap->id)
            ->assertJsonPath('data.0.price.total', 200.0)
            ->assertJsonPath('data.0.price.average_daily', 100.0)
            ->assertJsonPath('data.1.room.id', $expensive->id)
            ->assertJsonPath('data.1.price.total', 600.0);
    }

    public function test_applies_valid_coupon_to_results(): void
    {
        $room = Room::factory()->create(['daily_price' => 100]);
        Coupon::factory()->create(['code' => 'BUSCA10', 'value' => 10]);

        $this->getJson(self::API.'/availability?'.http_build_query([
            'check_in' => $this->day(5),
            'check_out' => $this->day(7),
            'coupon_code' => 'busca10',
        ]))
            ->assertOk()
            ->assertJsonPath('data.0.room.id', $room->id)
            ->assertJsonPath('data.0.coupon_applied', 'BUSCA10')
            ->assertJsonPath('data.0.price.total', 180.0);
    }

    public function test_validates_period(): void
    {
        $this->getJson(self::API.'/availability?check_in='.$this->day(5).'&check_out='.$this->day(3))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_out');
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    public function test_results_are_paginated(): void
    {
        Room::factory()->count(3)->create();

        $this->getJson(self::API.'/availability?check_in='.$this->day(5).'&check_out='.$this->day(6).'&per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.results', 3)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_query_count_does_not_grow_with_the_number_of_rooms(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(self::API.'/availability?check_in='.$this->day(5).'&check_out='.$this->day(7))->assertOk();

            return count(DB::getQueryLog());
        };

        Room::factory()->count(2)->create();
        $few = $count();
        Room::factory()->count(20)->create();

        $this->assertSame($few, $count());
    }
}
