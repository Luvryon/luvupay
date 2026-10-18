<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OpenAI-compatible client for Nebius AI (https://api.nebius.com/v1).
 * Retries with exponential-style delays, 30s timeout, structured logging to ai_request_logs.
 */
class NebiusClient implements NebiusAIContract
{
    public function chatJson(string $featureType, array $messages, ?string $merchantId = null): array
    {
        if (! config('nebius.enabled')) {
            throw new RuntimeException('Nebius AI is disabled (NEBIUS_ENABLED=false).');
        }

        $apiKey = (string) config('nebius.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('NEBIUS_API_KEY is not configured.');
        }

        $payload = [
            'model' => config('nebius.model'),
            'messages' => $messages,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $logId = (string) Str::uuid();
        $started = microtime(true);
        $this->writeLog($logId, $featureType, $merchantId, $payload, null, null, null, 'pending');

        $attempts = max(1, (int) config('nebius.retries', 3));
        $delays = config('nebius.retry_ms', [250, 750, 2000]);
        $lastError = null;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = Http::baseUrl(rtrim((string) config('nebius.base_url'), '/'))
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->timeout((int) config('nebius.timeout', 30))
                    ->post('/chat/completions', $payload);

                $latency = (int) round((microtime(true) - $started) * 1000);

                if ($response->successful()) {
                    $json = $response->json() ?? [];
                    $content = data_get($json, 'choices.0.message.content', '{}');
                    $tokens = (int) data_get($json, 'usage.total_tokens', 0) ?: null;
                    $this->writeLog($logId, $featureType, $merchantId, $payload, $json, $tokens, $latency, 'success');

                    return [
                        'content' => is_string($content) ? $content : json_encode($content),
                        'tokens_used' => $tokens,
                        'raw' => $json,
                    ];
                }

                $lastError = 'HTTP '.$response->status().': '.Str::limit($response->body(), 300);
                if ($response->status() < 500 && $response->status() !== 429) {
                    break; // non-retryable
                }
            } catch (ConnectionException $e) {
                $lastError = $e->getMessage();
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                break;
            }

            if ($i < $attempts - 1) {
                usleep(((int) ($delays[$i] ?? 1000)) * 1000);
            }
        }

        $latency = (int) round((microtime(true) - $started) * 1000);
        $this->writeLog($logId, $featureType, $merchantId, $payload, null, null, $latency, 'error', $lastError);
        Log::warning('nebius.chat_failed', ['feature' => $featureType, 'error' => $lastError]);

        throw new RuntimeException('Nebius AI request failed: '.($lastError ?? 'unknown'));
    }

    private function writeLog(
        string $id,
        string $featureType,
        ?string $merchantId,
        array $request,
        ?array $response,
        ?int $tokens,
        ?int $latency,
        string $status,
        ?string $error = null,
    ): void {
        try {
            \Illuminate\Support\Facades\DB::table('ai_request_logs')->updateOrInsert(
                ['id' => $id],
                [
                    'feature_type' => $featureType,
                    'input_hash' => hash('sha256', json_encode($request)),
                    'request_payload' => json_encode($this->redact($request)),
                    'response_payload' => $response ? json_encode($response) : null,
                    'tokens_used' => $tokens,
                    'cost_usd' => null,
                    'latency_ms' => $latency,
                    'status' => $status,
                    'error_message' => $error,
                    'merchant_id' => $merchantId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } catch (Throwable $e) {
            Log::debug('nebius.log_write_failed', ['msg' => $e->getMessage()]);
        }
    }

    private function redact(array $payload): array
    {
        // Keep structure; strip oversized message bodies if needed later.
        return $payload;
    }
}
