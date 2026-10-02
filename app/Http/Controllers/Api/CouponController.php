<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CouponController extends Controller
{
    /** Admin vê todos os cupons; demais usuários veem os globais e os do próprio hotel. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $coupons = Coupon::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where(
                fn ($q) => $q->whereNull('hotel_id')->orWhere('hotel_id', $user->hotel_id)
            ))
            ->orderBy('code')
            ->get();

        return CouponResource::collection($coupons);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $hotelId = $request->validated('hotel_id');

        Gate::authorize('create', [Coupon::class, $hotelId === null ? null : (int) $hotelId]);

        $coupon = Coupon::create($request->validated());

        return (new CouponResource($coupon->refresh()))->response()->setStatusCode(201);
    }

    public function destroy(Coupon $coupon): Response
    {
        Gate::authorize('delete', $coupon);

        $coupon->delete();

        return response()->noContent();
    }
}
