<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Gestão da equipe do hotel:
 *  - admin gerencia qualquer usuário;
 *  - manager gerencia os recepcionistas do próprio hotel e o próprio cadastro
 *    (criar ou promover gerentes e administradores é exclusivo do admin);
 *  - receptionist apenas consulta o próprio cadastro.
 */
class UserPolicy
{
    /** Gerente consulta toda a equipe do próprio hotel (como na listagem), mas só gerencia recepcionistas. */
    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id
            || $actor->isAdmin()
            || ($actor->role === UserRole::Manager && ! $target->isAdmin() && $target->hotel_id === $actor->hotel_id);
    }

    /** Pode atribuir o perfil $role no hotel $hotelId? */
    public function assign(User $actor, UserRole $role, ?int $hotelId): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->role === UserRole::Manager
            && $role === UserRole::Receptionist
            && $hotelId === $actor->hotel_id;
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id
            ? $actor->role !== UserRole::Receptionist
            : $this->manages($actor, $target);
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
            && $target->role === UserRole::Receptionist
            && $target->hotel_id === $actor->hotel_id;
    }
}
