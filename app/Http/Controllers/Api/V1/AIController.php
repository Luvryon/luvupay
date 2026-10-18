<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AI\ChurnPredictionRequest;
use App\Http\Requests\AI\ResalePriceSuggestionRequest;
use App\Jobs\AnalyzeChurnRiskJob;
use App\Jobs\CalculateOptimalPriceJob;
use App\Jobs\GenerateSubscriptionReportJob;
use App\Services\Nebius\ChurnPredictionService;
use App\Services\Nebius\DynamicPricingService;
use App\Services\Nebius\EcosystemHubService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AIController
{
    public function churnPrediction(ChurnPredictionRequest $request, ChurnPredictionService $svc): JsonResponse
    {
        $data = $request->validated();
        $async = (bool) ($data['async'] ?? false);
        unset($data['async']);
        $merchantId = $request->attributes->get('merchant_id');

        if ($async) {
            $key = 'ai:churn:'.Str::uuid();
            AnalyzeChurnRiskJob::dispatch($data, $merchantId, $key);

            return response()->json(['status' => 'queued', 'result_key' => $key], 202);
        }

        return response()->json($svc->predict($data, $merchantId)->toArray());
    }

    public function resalePriceSuggestion(ResalePriceSuggestionRequest $request, DynamicPricingService $svc): JsonResponse
    {
        $data = $request->validated();
        $async = (bool) ($data['async'] ?? false);
        unset($data['async']);
        $merchantId = $request->attributes->get('merchant_id');

        if ($async) {
            $key = 'ai:price:'.Str::uuid();
            CalculateOptimalPriceJob::dispatch($data, $merchantId, $key);

            return response()->json(['status' => 'queued', 'result_key' => $key], 202);
        }

        return response()->json($svc->suggestPrice($data, $merchantId)->toArray());
    }

    public function subscriptionHealthReport(Request $request, EcosystemHubService $svc): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'async' => 'nullable|boolean',
        ]);
        $merchantId = $request->attributes->get('merchant_id');

        if (! empty($data['async'])) {
            $key = 'ai:hub:'.Str::uuid();
            GenerateSubscriptionReportJob::dispatch($data['email'], $merchantId, $key);

            return response()->json(['status' => 'queued', 'result_key' => $key], 202);
        }

        return response()->json($svc->generateHealthReport($data['email'], null, $merchantId)->toArray());
    }

    public function jobResult(Request $request): JsonResponse
    {
        $key = $request->validate(['result_key' => 'required|string'])['result_key'];
        $payload = Cache::get($key);
        if (! $payload) {
            return response()->json(['status' => 'pending'], 202);
        }

        return response()->json(['status' => 'ready', 'data' => $payload]);
    }
}
