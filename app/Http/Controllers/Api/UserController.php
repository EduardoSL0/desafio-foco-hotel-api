<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/** Gestão da equipe do hotel (usuários e perfis de acesso). */
class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $actor = $request->user();

        abort_if($actor->role === UserRole::Receptionist, 403, 'Sem permissão para listar usuários.');

        $users = User::query()
            ->when(! $actor->isAdmin(), fn ($q) => $q->where('hotel_id', $actor->hotel_id))
            ->when($request->integer('hotel_id'), fn ($q, int $id) => $q->where('hotel_id', $id))
            ->when(UserRole::tryFrom($request->string('role')->value()), fn ($q, UserRole $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);
        $hotelId = $role === UserRole::Admin ? null : (int) $data['hotel_id'];

        Gate::authorize('assign', [User::class, $role, $hotelId]);

        $user = User::create([...$data, 'hotel_id' => $hotelId]);

        Log::info('user.created', ['user_id' => $user->id, 'role' => $role->value, 'by' => $request->user()->id]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        Gate::authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        Gate::authorize('update', $user);

        $data = $request->validated();
        $role = isset($data['role']) ? UserRole::from($data['role']) : $user->role;
        $hotelId = $role === UserRole::Admin
            ? null
            : (array_key_exists('hotel_id', $data) ? (int) $data['hotel_id'] : $user->hotel_id);

        if (isset($data['role']) || array_key_exists('hotel_id', $data)) {
            Gate::authorize('assign', [User::class, $role, $hotelId]);
        }

        $user->update([...$data, 'hotel_id' => $hotelId]);

        // Troca de senha invalida as sessões (tokens) existentes do usuário.
        if (isset($data['password'])) {
            $user->tokens()->delete();
        }

        Log::info('user.updated', ['user_id' => $user->id, 'fields' => array_keys($data), 'by' => $request->user()->id]);

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user): Response
    {
        Gate::authorize('delete', $user);

        $user->tokens()->delete();
        $user->delete();

        Log::info('user.deleted', ['user_id' => $user->id, 'by' => $request->user()->id]);

        return response()->noContent();
    }
}
