<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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

    /**
     * Regra de negócio recusada é resposta esperada (409/422), não falha do sistema:
     * vai para o log como INFO, sem stack trace, para não poluir os erros reais.
     */
    public function report(): void
    {
        Log::info('business.rejected', ['rule' => class_basename($this), 'message' => $this->getMessage(), 'status' => $this->status]);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], $this->status);
    }
}
