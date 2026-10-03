<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApiResource;
use App\Models\Hotel;
use App\Services\HotelReportService;
use App\Support\DateInput;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Painel de indicadores do hotel (ocupação, ADR, RevPAR, recebimentos). */
class HotelReportController extends Controller
{
    public function __invoke(Request $request, Hotel $hotel, HotelReportService $reports): JsonResponse
    {
        abort_unless($request->user()->canManageHotel($hotel->id), 403, 'Apenas administradores e gerentes do hotel acessam o relatório.');

        $validated = $request->validate([
            'from' => ['bail', 'sometimes', 'date_format:Y-m-d'],
            'to' => ['bail', 'sometimes', 'date_format:Y-m-d', ...DateInput::compareWith('after_or_equal', 'from', $request->query('from'))],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? now()->startOfMonth()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? now()->endOfMonth()->toDateString());

        abort_if($from->diffInDays($to) > 366, 422, 'O período máximo do relatório é de 1 ano.');

        return response()->json(['data' => $reports->build($hotel, $from, $to)], 200, [], ApiResource::JSON_OPTIONS);
    }
}
