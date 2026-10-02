<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    /** Cupons globais (sem hotel) só podem ser criados por administradores. */
    public function create(User $user, ?int $hotelId = null): bool
    {
        return $hotelId === null ? $user->isAdmin() : $user->canManageHotel($hotelId);
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $this->create($user, $coupon->hotel_id);
    }
}
