<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReserveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\LookupReserveRequest;
use App\Http\Requests\QuoteReserveRequest;
use App\Http\Requests\StoreReserveRequest;
use App\Http\Resources\ApiResource;
use App\Http\Resources\PublicReserveResource;
use App\Http\Resources\ReserveResource;
use App\Models\Guest;
use App\Models\Reserve;
use App\Models\User;
use App\Services\ReserveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReserveController extends Controller
{
    public function __construct(private readonly ReserveService $service) {}

    /** Lista reservas visíveis ao usuário (admin: todas; demais: do próprio hotel). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $reserves = Reserve::query()
            ->with(['hotel', 'room', 'coupon', 'guests', 'payments'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('hotel_id', $user->hotel_id))
            ->when($request->integer('hotel_id'), fn ($q, int $id) => $q->where('hotel_id', $id))
            ->when($request->integer('room_id'), fn ($q, int $id) => $q->where('room_id', $id))
            ->when(ReserveStatus::tryFrom($this->queryText($request, 'status')), fn ($q, ReserveStatus $s) => $q->where('status', $s))
            ->orderByDesc('check_in')
            ->paginate($perPage)
            ->withQueryString();

        return ReserveResource::collection($reserves);
    }

    public function show(Reserve $reserve): ReserveResource
    {
        Gate::authorize('view', $reserve);

        return new ReserveResource($reserve->load(['hotel', 'room', 'coupon', 'guests', 'dailies', 'payments']));
    }

    /**
     * "Minha reserva": o hóspede consulta com o localizador + sobrenome de um dos hóspedes.
     * A resposta é a mesma (404) para código inexistente ou sobrenome errado, para não
     * revelar se um localizador existe. Devolve a visão pública (sem contatos dos hóspedes
     * nem lista de pagamentos).
     */
    public function lookup(LookupReserveRequest $request): PublicReserveResource|JsonResponse
    {
        $reserve = Reserve::query()
            ->where('code', $request->validated('code'))
            ->with(['hotel', 'room', 'coupon', 'guests', 'dailies', 'payments'])
            ->first();

        // Comparação em PHP (mb_strtolower): acentos funcionam igual em MySQL e SQLite.
        $lastName = mb_strtolower(trim($request->validated('last_name')));
        $matches = $reserve?->guests->contains(fn (Guest $g) => mb_strtolower(trim($g->last_name)) === $lastName);

        if (! $matches) {
            return response()->json(['message' => 'Reserva não encontrada. Confira o localizador e o sobrenome.'], 404);
        }

        return new PublicReserveResource($reserve);
    }

    /** Cotação: calcula diárias, descontos e taxas sem criar a reserva. */
    public function quote(QuoteReserveRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->quote($request->validated())], 200, [], ApiResource::JSON_OPTIONS);
    }

    /**
     * A equipe do hotel recebe a reserva completa; o público (motor de reservas) recebe a
     * visão pública, que não devolve contatos de hóspedes já cadastrados.
     */
    public function store(StoreReserveRequest $request): JsonResponse
    {
        /** @var User|null $actor */
        $actor = $request->user('sanctum');
        $reserve = $this->service->create($request->validated(), $actor);

        $resource = $actor?->worksAt($reserve->hotel_id)
            ? new ReserveResource($reserve)
            : new PublicReserveResource($reserve);

        return $resource->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Reserve $reserve): ReserveResource
    {
        Gate::authorize('cancel', $reserve);

        $reserve = $this->service->cancel($reserve, $request->user());

        return new ReserveResource($reserve->load(['hotel', 'room', 'coupon', 'guests', 'dailies', 'payments']));
    }
}
