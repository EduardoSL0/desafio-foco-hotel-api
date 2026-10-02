<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteReserveRequest;
use App\Http\Requests\StoreReserveRequest;
use App\Http\Resources\ApiResource;
use App\Http\Resources\ReserveResource;
use App\Models\Reserve;
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
            ->when($request->string('status')->value(), fn ($q, string $s) => $q->where('status', $s))
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

    /** Cotação: calcula diárias, descontos e taxas sem criar a reserva. */
    public function quote(QuoteReserveRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->service->quote($request->validated())], 200, [], ApiResource::JSON_OPTIONS);
    }

    public function store(StoreReserveRequest $request): JsonResponse
    {
        $reserve = $this->service->create($request->validated());

        return (new ReserveResource($reserve))->response()->setStatusCode(201);
    }

    public function cancel(Reserve $reserve): ReserveResource
    {
        Gate::authorize('cancel', $reserve);

        $reserve = $this->service->cancel($reserve);

        return new ReserveResource($reserve->load(['hotel', 'room', 'coupon', 'guests', 'dailies', 'payments']));
    }
}
