<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            LogApiRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Respostas de erro padronizadas, em português e sem detalhes internos.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Recurso não encontrado.'], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Não autenticado. Faça login em POST /api/v1/auth/login e envie o token no cabeçalho Authorization: Bearer <token>.'], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $custom = $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.';

                return response()->json(['message' => $custom ? $e->getMessage() : 'Você não tem permissão para esta ação.'], 403);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                $wait = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json(['message' => "Muitas requisições. Tente novamente em {$wait} segundo(s)."], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Método HTTP não permitido para esta rota.'], 405, $e->getHeaders());
            }
        });

        // Demais erros HTTP (ex.: abort(403, '...') nos controllers): só a mensagem, sem stack trace mesmo com APP_DEBUG.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                $fallback = [400 => 'Requisição inválida.', 403 => 'Você não tem permissão para esta ação.', 413 => 'Requisição muito grande (máximo de 10 MB).'];
                $message = $e->getMessage() !== '' ? $e->getMessage() : ($fallback[$e->getStatusCode()] ?? 'Não foi possível processar a requisição.');

                return response()->json(['message' => $message], $e->getStatusCode(), $e->getHeaders());
            }
        });
    })->create();
