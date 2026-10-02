<?php

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

class PromotionPolicy
{
    public function create(User $user, int $hotelId): bool
    {
        return $user->canManageHotel($hotelId);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->canManageHotel($promotion->hotel_id);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->canManageHotel($promotion->hotel_id);
    }
}
