<?php

namespace Database\Factories;

use App\Enums\ReserveStatus;
use App\Models\Reserve;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Reserve> */
class ReserveFactory extends Factory
{
    protected $model = Reserve::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'hotel_id' => fn (array $attributes) => Room::withTrashed()->findOrFail($attributes['room_id'])->hotel_id,
            'check_in' => now()->addDays(10)->toDateString(),
            'check_out' => now()->addDays(13)->toDateString(),
            'subtotal' => 300,
            'discount' => 0,
            'fees' => 0,
            'total' => 300,
            'status' => ReserveStatus::Pending,
            'source' => 'api',
        ];
    }

    public function forRoom(Room $room): static
    {
        return $this->state(['room_id' => $room->id, 'hotel_id' => $room->hotel_id]);
    }

    public function between(string $checkIn, string $checkOut): static
    {
        return $this->state(['check_in' => $checkIn, 'check_out' => $checkOut]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => ReserveStatus::Cancelled]);
    }
}
