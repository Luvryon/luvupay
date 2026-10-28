<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Demo\Concerns\DemoSupport;
use App\Services\Dashboard\MerchantMetrics;
use App\Services\Demo\MonthSimulator;
use App\Support\MerchantProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /api/v1/dashboard/demo/simulate-month — "Simulate 30 days". Gated by demo.simulator. */
final class DemoMonthController
{
    use DemoSupport;

    public function __invoke(Request $request, MonthSimulator $sim, MerchantMetrics $metrics): JsonResponse
    {
        $merchantId = $this->merchantId($request);
        $data = $request->validate([
            'rows' => ['nullable', 'array', 'max:20'],
            'rows.*.name' => ['nullable', 'string', 'max:120'],
            'rows.*.email' => ['nullable', 'email', 'max:190'],
        ]);

        $project = MerchantProject::current();
        if (! $project->isSet()) {
            return response()->json(['ok' => false, 'message' => 'Create a project first'], 422);
        }

        $result = $sim->run($merchantId, $data['rows'] ?? []);
        $summary = $metrics->compute($merchantId);

        return response()->json([
            'ok' => true,
            'message' => 'Simulated 30 days: '.$result['created'].' subscribers across '.count($result['outcomes']).' lifecycle outcomes.',
            'result' => $result,
            'summary' => $summary,
        ], 201);
    }
}
