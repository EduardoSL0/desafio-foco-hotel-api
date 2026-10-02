<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Gestão da equipe do hotel:
 *  - admin gerencia qualquer usuário;
 *  - manager gerencia gerentes/recepcionistas do próprio hotel (nunca administradores);
 *  - receptionist apenas consulta o próprio cadastro.
 */
class UserPolicy
{
    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $this->manages($actor, $target);
    }

    /** Pode atribuir o perfil $role no hotel $hotelId? */
    public function assign(User $actor, UserRole $role, ?int $hotelId): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->role === UserRole::Manager
            && $role !== UserRole::Admin
            && $hotelId === $actor->hotel_id;
    }

    public function update(User $actor, User $target): bool
    {
        return $this->manages($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $this->manages($actor, $target);
    }

    private function manages(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->role === UserRole::Manager
            && ! $target->isAdmin()
            && $target->hotel_id === $actor->hotel_id;
    }
}
