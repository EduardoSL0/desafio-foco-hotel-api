<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /** Hash bcrypt de uma senha aleatória descartada, usado quando o e-mail não existe. */
    private const DUMMY_HASH = '$2y$12$NarXspsjVWFS.Mb1sgCEn.41uSb4yL9pBBvpQBz.hLHPAutlvaYNq';

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        // Mesmo sem usuário o hash é verificado (contra um hash fictício): o tempo de resposta
        // não revela quais e-mails estão cadastrados.
        $valid = Hash::check($request->validated('password'), $user?->password ?? self::DUMMY_HASH);

        if (! $user || ! $valid) {
            Log::warning('auth.failed', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        $ttl = (int) config('hotel.auth.token_ttl_hours', 8);
        $token = $user->createToken('api', ['*'], now()->addHours($ttl))->plainTextToken;

        Log::info('auth.login', ['user_id' => $user->id]);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => $ttl * 3600,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
