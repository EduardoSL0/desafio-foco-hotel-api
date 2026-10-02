<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Log de acesso da API (storage/logs/api-*.log). O corpo da requisição não é
 * registrado para não persistir dados pessoais ou credenciais.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $response = $next($request);

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
}
