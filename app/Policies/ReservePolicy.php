<?php

namespace App\Policies;

use App\Models\Reserve;
use App\Models\User;

class ReservePolicy
{
    public function view(User $user, Reserve $reserve): bool
    {
        return $user->worksAt($reserve->hotel_id);
    }

    public function cancel(User $user, Reserve $reserve): bool
    {
        return $user->canManageHotel($reserve->hotel_id);
    }

    public function pay(User $user, Reserve $reserve): bool
    {
        return $user->worksAt($reserve->hotel_id);
    }

    /** Estorno devolve dinheiro: restrito a administradores e gerentes do hotel. */
    public function refund(User $user, Reserve $reserve): bool
    {
        return $user->canManageHotel($reserve->hotel_id);
    }
}
