<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Reserve;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PaymentController extends Controller
{
    public function index(Reserve $reserve): AnonymousResourceCollection
    {
        Gate::authorize('view', $reserve);

        return PaymentResource::collection($reserve->payments()->orderBy('id')->get());
    }

    public function store(StorePaymentRequest $request, Reserve $reserve, PaymentService $payments): JsonResponse
    {
        Gate::authorize('pay', $reserve);

        $payment = $payments->register($reserve, $request->validated(), actor: $request->user());

        return (new PaymentResource($payment))
            ->additional(['reserve' => ['status' => $reserve->status->value, 'balance' => $reserve->balance()]])
            ->response()
            ->setStatusCode(201);
    }

    public function refund(Request $request, Reserve $reserve, Payment $payment, PaymentService $payments): PaymentResource
    {
        Gate::authorize('refund', $reserve);

        $payment = $payments->refund($reserve, $payment, $request->user());

        return (new PaymentResource($payment))
            ->additional(['reserve' => ['status' => $reserve->status->value, 'balance' => $reserve->balance()]]);
    }
}
