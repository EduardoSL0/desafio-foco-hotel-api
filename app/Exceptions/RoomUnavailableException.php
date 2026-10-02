<?php

namespace App\Exceptions;

class RoomUnavailableException extends BusinessException
{
    protected int $status = 409;

    public function __construct()
    {
        parent::__construct('Não há disponibilidade para este quarto no período informado.');
    }
}
