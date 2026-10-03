<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\RoomHasActiveReservesException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\StoreRoomRequest;
use App\Http\Requests\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $rooms = Room::query()
            ->with('hotel')
            ->when($request->integer('hotel_id'), fn ($q, int $hotelId) => $q->where('hotel_id', $hotelId))
            ->when($this->queryText($request, 'search'), fn ($q, string $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return RoomResource::collection($rooms);
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        Gate::authorize('create', [Room::class, (int) $request->validated('hotel_id')]);

        $room = Room::create($request->validated());

        Log::info('room.created', ['room_id' => $room->id, 'user_id' => $request->user()->id]);

        return (new RoomResource($room->load('hotel')))->response()->setStatusCode(201);
    }

    public function show(Room $room): RoomResource
    {
        return new RoomResource($room->load('hotel'));
    }

    public function update(UpdateRoomRequest $request, Room $room, AvailabilityService $availability): RoomResource
    {
        Gate::authorize('update', $room);

        // Reduzir o inventário abaixo das reservas futuras já vendidas causaria overbooking.
        if ($request->has('inventory') && (int) $request->validated('inventory') < $room->inventory) {
            $lastCheckOut = $room->reserves()->active()->where('check_out', '>', CarbonImmutable::today())->max('check_out');

            if ($lastCheckOut !== null) {
                $peak = $availability->peakOccupation($room, CarbonImmutable::today(), CarbonImmutable::parse($lastCheckOut));

                if ((int) $request->validated('inventory') < $peak) {
                    throw ValidationException::withMessages([
                        'inventory' => "Há {$peak} unidade(s) reservadas simultaneamente em datas futuras; o inventário não pode ser menor que isso.",
                    ]);
                }
            }
        }

        $room->update($request->validated());

        Log::info('room.updated', ['room_id' => $room->id, 'user_id' => $request->user()->id, 'fields' => array_keys($request->validated())]);

        return new RoomResource($room->load('hotel'));
    }

    public function destroy(Request $request, Room $room): Response
    {
        Gate::authorize('delete', $room);

        $hasUpcoming = $room->reserves()
            ->active()
            ->where('check_out', '>', CarbonImmutable::today())
            ->exists();

        if ($hasUpcoming) {
            throw new RoomHasActiveReservesException;
        }

        $room->delete();

        Log::info('room.deleted', ['room_id' => $room->id, 'user_id' => $request->user()->id]);

        return response()->noContent();
    }

    public function availability(AvailabilityRequest $request, Room $room, AvailabilityService $availability): JsonResponse
    {
        $checkIn = CarbonImmutable::parse($request->validated('check_in'));
        $checkOut = CarbonImmutable::parse($request->validated('check_out'));

        return response()->json([
            'data' => [
                'room_id' => $room->id,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'inventory' => $room->inventory,
                'available_units' => $availability->availableUnits($room, $checkIn, $checkOut),
            ],
        ]);
    }
}
