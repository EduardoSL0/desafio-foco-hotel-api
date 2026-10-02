<?php

namespace App\Exceptions;

class RoomHasActiveReservesException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('O quarto possui reservas futuras ativas e não pode ser removido.');
    }
}
