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

        // Data ausente é completada pelo mês da data informada (ou pelo mês atual, sem nenhuma):
        // só "from" em março => março inteiro, e não "de março até o fim do mês atual".
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : null;
        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : null;
        $from ??= ($to ?? CarbonImmutable::now())->startOfMonth();
        $to ??= $from->endOfMonth();

        abort_if($to->lt($from), 422, 'A data final do relatório não pode ser anterior à inicial.');
        abort_if($from->diffInDays($to) > 366, 422, 'O período máximo do relatório é de 1 ano.');

        return response()->json(['data' => $reports->build($hotel, $from, $to)], 200, [], ApiResource::JSON_OPTIONS);
    }
}
