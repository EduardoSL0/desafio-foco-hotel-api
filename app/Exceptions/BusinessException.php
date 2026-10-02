<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Violação de regra de negócio. O Laravel chama render() automaticamente,
 * devolvendo uma resposta JSON padronizada com o status HTTP adequado.
 */
abstract class BusinessException extends Exception
{
    protected int $status = 422;

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
