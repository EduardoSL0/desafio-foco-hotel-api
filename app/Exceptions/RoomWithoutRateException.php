<?php

namespace App\Exceptions;

class RoomWithoutRateException extends BusinessException
{
    protected int $status = 422;

    public function __construct()
    {
        parent::__construct('O quarto não possui tarifa (daily_price) configurada.');
    }
}
