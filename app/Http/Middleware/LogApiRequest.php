<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log de acesso da API (storage/logs/api-*.log) com correlação por Request-ID.
 *
 * O ID vem do cabeçalho X-Request-Id (quando enviado por um gateway/cliente) ou é
 * gerado aqui; ele é devolvido na resposta e incluído em todos os logs da requisição,
 * permitindo rastrear um erro reportado pelo cliente até as linhas de log.
 * O corpo da requisição não é registrado para não persistir dados pessoais ou credenciais.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = $this->requestId($request);

        Log::shareContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        Log::channel('api')->info('api.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'ip' => $request->ip(),
        ]);

        return $response;
    }

    private function requestId(Request $request): string
    {
        $incoming = (string) $request->headers->get('X-Request-Id', '');

        // Aceita apenas IDs simples para evitar injeção de conteúdo nos logs.
        return preg_match('/^[A-Za-z0-9._-]{8,64}$/', $incoming) ? $incoming : (string) Str::uuid();
    }
}
