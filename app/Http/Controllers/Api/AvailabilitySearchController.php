<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchAvailabilityRequest;
use App\Http\Resources\ApiResource;
use App\Services\AvailabilitySearchService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AvailabilitySearchController extends Controller
{
    public function __invoke(SearchAvailabilityRequest $request, AvailabilitySearchService $search): JsonResponse
    {
        $checkIn = CarbonImmutable::parse($request->validated('check_in'));
        $checkOut = CarbonImmutable::parse($request->validated('check_out'));
        $guests = (int) $request->validated('guests', 1);

        $results = $search->search(
            $checkIn,
            $checkOut,
            $guests,
            $request->validated('hotel_id') ? (int) $request->validated('hotel_id') : null,
            $request->validated('coupon_code'),
        );

        // Resultados já ordenados por preço; a página é recortada depois da ordenação.
        $perPage = (int) $request->validated('per_page', 20);
        $page = (int) $request->validated('page', 1);
        $total = count($results);

        return response()->json([
            'data' => array_slice($results, ($page - 1) * $perPage, $perPage),
            'meta' => [
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'guests' => $guests,
                'results' => $total,
                'current_page' => $page,
                'per_page' => $perPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ], 200, [], ApiResource::JSON_OPTIONS);
    }
}
