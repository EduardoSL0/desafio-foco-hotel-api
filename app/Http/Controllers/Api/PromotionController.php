<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PromotionController extends Controller
{
    /** Admin vê todas; demais usuários veem as do próprio hotel. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $promotions = Promotion::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('hotel_id', $user->hotel_id))
            ->when($request->integer('hotel_id'), fn ($q, int $id) => $q->where('hotel_id', $id))
            ->when($request->boolean('only_active'), fn ($q) => $q->where('active', true)->where('ends_at', '>=', today()))
            ->orderByDesc('starts_at')
            ->get();

        return PromotionResource::collection($promotions);
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        Gate::authorize('create', [Promotion::class, (int) $request->validated('hotel_id')]);

        $promotion = Promotion::create($request->validated());

        Log::info('promotion.created', ['promotion_id' => $promotion->id, 'by' => $request->user()->id]);

        return (new PromotionResource($promotion->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, Promotion $promotion): PromotionResource
    {
        abort_unless($request->user()->worksAt($promotion->hotel_id), 403);

        return new PromotionResource($promotion);
    }

    public function update(PromotionRequest $request, Promotion $promotion): PromotionResource
    {
        Gate::authorize('update', $promotion);

        $promotion->update($request->validated());

        Log::info('promotion.updated', ['promotion_id' => $promotion->id, 'by' => $request->user()->id]);

        return new PromotionResource($promotion);
    }

    public function destroy(Request $request, Promotion $promotion): Response
    {
        Gate::authorize('delete', $promotion);

        $promotion->delete();

        Log::info('promotion.deleted', ['promotion_id' => $promotion->id, 'by' => $request->user()->id]);

        return response()->noContent();
    }
}
