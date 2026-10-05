<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suporte ao cabeçalho "Idempotency-Key" (padrão usado por gateways como Stripe).
 *
 * Se o cliente reenviar a mesma requisição (ex.: timeout de rede após a reserva já
 * ter sido criada), a resposta original é devolvida em vez de criar uma reserva
 * duplicada. Reutilizar a mesma chave com um corpo diferente retorna 422, e uma
 * requisição com a mesma chave ainda em processamento retorna 409.
 *
 * O cabeçalho é opcional: sem ele, a rota se comporta normalmente.
 */
class Idempotency
{
    private const TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');

        if ($key === '') {
            return $next($request);
        }

        if (! preg_match('/^[A-Za-z0-9._:-]{8,100}$/', $key)) {
            return response()->json(['message' => 'Idempotency-Key inválida (use de 8 a 100 caracteres alfanuméricos, ".", "_", ":" ou "-").'], 400);
        }

        // Escopo por rota e por usuário (ou IP, em rotas públicas) evita colisão entre clientes.
        $scope = hash('sha256', implode('|', [$request->method(), $request->path(), $request->user('sanctum')?->getAuthIdentifier() ?? $request->ip(), $key]));
        $fingerprint = $this->fingerprint($request);
        $cacheKey = "idempotency:{$scope}";

        if ($cached = Cache::get($cacheKey)) {
            return $this->replay($cached, $fingerprint);
        }

        $lock = Cache::lock("{$cacheKey}:lock", 30);

        if (! $lock->get()) {
            return response()->json(['message' => 'Uma requisição com esta Idempotency-Key ainda está em processamento.'], 409);
        }

        try {
            // Verifica novamente após obter o lock (outra requisição pode ter concluído).
            if ($cached = Cache::get($cacheKey)) {
                return $this->replay($cached, $fingerprint);
            }

            $response = $next($request);

            // Erros de servidor não são armazenados: o cliente pode tentar de novo com a mesma chave.
            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, [
                    'fingerprint' => $fingerprint,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                    'content_type' => $response->headers->get('Content-Type', 'application/json'),
                ], self::TTL_SECONDS);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * Impressão digital do conteúdo: em JSON compara os dados (ordem das chaves e espaços
     * não importam); em outros formatos, o corpo bruto.
     */
    private function fingerprint(Request $request): string
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data)) {
            return hash('sha256', $request->getContent());
        }

        $normalize = function (array $value) use (&$normalize): array {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($v) => is_array($v) ? $normalize($v) : $v, $value);
        };

        return hash('sha256', json_encode($normalize($data)));
    }

    private function replay(array $cached, string $fingerprint): Response
    {
        if (! hash_equals($cached['fingerprint'], $fingerprint)) {
            return response()->json(['message' => 'Esta Idempotency-Key já foi usada com outro conteúdo de requisição.'], 422);
        }

        Log::info('idempotency.replayed');

        return response($cached['body'], $cached['status'], [
            'Content-Type' => $cached['content_type'],
            'Idempotent-Replayed' => 'true',
        ]);
    }
}
