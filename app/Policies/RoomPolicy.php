<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function create(User $user, int $hotelId): bool
    {
        return $user->canManageHotel($hotelId);
    }

    public function update(User $user, Room $room): bool
    {
        return $user->canManageHotel($room->hotel_id);
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->canManageHotel($room->hotel_id);
    }
}
