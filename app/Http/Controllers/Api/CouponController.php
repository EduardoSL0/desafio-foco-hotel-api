<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCouponRequest;
use App\Http\Resources\CouponResource;
use App\Models\Coupon;
use App\Models\Reserve;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

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

        Log::info('coupon.created', ['coupon_id' => $coupon->id, 'code' => $coupon->code, 'by' => $request->user()->id]);

        return (new CouponResource($coupon->refresh()))->response()->setStatusCode(201);
    }

    /**
     * Cupom ainda não usado é excluído. Cupom já usado em reservas é apenas desativado,
     * para não perder o histórico de qual cupom cada reserva utilizou.
     */
    public function destroy(Request $request, Coupon $coupon): Response
    {
        Gate::authorize('delete', $coupon);

        if (Reserve::query()->where('coupon_id', $coupon->id)->exists()) {
            $coupon->update(['active' => false]);
            Log::info('coupon.deactivated', ['coupon_id' => $coupon->id, 'by' => $request->user()->id]);
        } else {
            $coupon->delete();
            Log::info('coupon.deleted', ['coupon_id' => $coupon->id, 'by' => $request->user()->id]);
        }

        return response()->noContent();
    }
}
