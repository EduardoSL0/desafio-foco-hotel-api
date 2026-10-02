<?php

namespace App\Exceptions;

class ReserveCancelledException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('A reserva está cancelada.');
    }
}
