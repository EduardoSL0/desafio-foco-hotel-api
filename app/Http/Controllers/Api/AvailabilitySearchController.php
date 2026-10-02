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

        return response()->json([
            'data' => $results,
            'meta' => [
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'guests' => $guests,
                'results' => count($results),
            ],
        ], 200, [], ApiResource::JSON_OPTIONS);
    }
}
