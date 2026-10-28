<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * OpenAI-compatible client for Nebius Token Factory running NVIDIA Nemotron.
 *
 * - Retries timeouts, 429 and 5xx with backoff.
 * - If the endpoint rejects the optional request fields (JSON mode, thinking switch) it retries once with a plain request.
 * - Strips the reasoning trace and code fences, so callers get clean text or a validated JSON object.
 * - Every call is written to ai_request_logs (pending, success or error).
 */
class NebiusClient implements NebiusAIContract
{
    public function chatJson(string $featureType, array $messages, ?string $merchantId = null): array
    {
        $result = $this->complete($featureType, $messages, $merchantId, jsonMode: true);
        $data = $this->extractJson($result['content']);

        if ($data === null) {
            $this->markLogError($result['log_id'], 'Model output was not valid JSON');
            throw new RuntimeException('Nebius returned output that is not valid JSON: '.Str::limit($result['content'], 160));
        }

        unset($result['log_id']);

        return ['content' => json_encode($data), 'data' => $data] + $result;
    }

    public function chatText(string $featureType, array $messages, ?string $merchantId = null): array
    {
        $result = $this->complete($featureType, $messages, $merchantId, jsonMode: false);
        unset($result['log_id']);

        return $result;
    }

    /** @return array{content:string, tokens_used:int|null, model:string|null, latency_ms:int, raw:array, log_id:string} */
    private function complete(string $featureType, array $messages, ?string $merchantId, bool $jsonMode): array
    {
        if (! config('nebius.enabled')) {
            throw new RuntimeException('Nebius AI is disabled (NEBIUS_ENABLED=false)');
        }

        $apiKey = trim((string) config('nebius.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('NEBIUS_API_KEY is not configured');
        }

        $plain = [
            'model' => config('nebius.model'),
            'messages' => $messages,
            'temperature' => (float) config('nebius.temperature', 0.2),
            'max_tokens' => (int) config('nebius.max_tokens', 2048),
        ];
        $payload = $plain;
        if ($jsonMode && config('nebius.json_mode')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        if (config('nebius.disable_thinking')) {
            $payload['chat_template_kwargs'] = ['enable_thinking' => false];
        }

        $logId = (string) Str::uuid();
        $started = microtime(true);
        $this->writeLog($logId, $featureType, $merchantId, $payload, null, null, null, 'pending');

        $attempts = max(1, (int) config('nebius.retries', 3));
        $delays = config('nebius.retry_ms', [250, 750, 2000]);
        $lastError = 'unknown error';
        $downgraded = false;

        for ($i = 0; $i < $attempts; $i++) {
            try {
                $response = $this->post($apiKey, $payload);

                // The endpoint does not know an optional field: drop them once and try again straight away
                if ($response->status() === 400 && ! $downgraded && $payload !== $plain) {
                    $downgraded = true;
                    $payload = $plain;
                    $response = $this->post($apiKey, $payload);
                }

                if ($response->successful()) {
                    $json = $response->json() ?? [];
                    $content = $this->cleanContent(data_get($json, 'choices.0.message.content'));
                    if ($content === '') {
                        $lastError = 'Empty completion (finish_reason: '.(string) data_get($json, 'choices.0.finish_reason', 'unknown').'). Raise NEBIUS_MAX_TOKENS';
                        break;
                    }

                    $tokens = (int) data_get($json, 'usage.total_tokens', 0) ?: null;
                    $latency = (int) round((microtime(true) - $started) * 1000);
                    $this->writeLog($logId, $featureType, $merchantId, $payload, $json, $tokens, $latency, 'success');

                    return [
                        'content' => $content,
                        'tokens_used' => $tokens,
                        'model' => data_get($json, 'model'),
                        'latency_ms' => $latency,
                        'raw' => $json,
                        'log_id' => $logId,
                    ];
                }

                $lastError = 'HTTP '.$response->status().': '.Str::limit($response->body(), 300);
                if ($response->status() < 500 && $response->status() !== 429) {
                    break; // 401, 403, 404 and other client errors will not fix themselves
                }
            } catch (ConnectionException $e) {
                $lastError = 'Connection failed: '.$e->getMessage();
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

        throw new RuntimeException('Nebius AI request failed: '.$lastError);
    }

    private function post(string $apiKey, array $payload): Response
    {
        return Http::baseUrl(rtrim((string) config('nebius.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('nebius.timeout', 45))
            ->post('/chat/completions', $payload);
    }

    /** Removes <think> blocks and markdown fences that reasoning models sometimes add */
    private function cleanContent(mixed $content): string
    {
        if (is_array($content)) {
            $content = collect($content)->pluck('text')->filter()->implode('');
        }
        $text = (string) $content;
        $text = preg_replace('#<think>.*?</think>#si', '', $text) ?? $text;
        $text = preg_replace('#^.*?</think>#si', '', $text) ?? $text; // opening tag cut off by the template
        $text = trim($text);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/si', $text, $m)) {
            $text = trim($m[1]);
        }

        return $text;
    }

    /** @return array<string,mixed>|null */
    private function extractJson(string $text): ?array
    {
        $decoded = json_decode($text, true);
        if (is_array($decoded) && ! array_is_list($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
            if (is_array($decoded) && ! array_is_list($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function writeLog(string $id, string $featureType, ?string $merchantId, array $request, ?array $response, ?int $tokens, ?int $latency, string $status, ?string $error = null): void
    {
        try {
            DB::table('ai_request_logs')->updateOrInsert(['id' => $id], [
                'feature_type' => $featureType,
                'input_hash' => hash('sha256', json_encode($request)),
                'request_payload' => json_encode($request),
                'response_payload' => $response ? json_encode($response) : null,
                'tokens_used' => $tokens,
                'cost_usd' => null,
                'latency_ms' => $latency,
                'status' => $status,
                'error_message' => $error,
                'merchant_id' => $merchantId,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::debug('nebius.log_write_failed', ['msg' => $e->getMessage()]);
        }
    }

    private function markLogError(string $id, string $error): void
    {
        try {
            DB::table('ai_request_logs')->where('id', $id)->update(['status' => 'error', 'error_message' => $error, 'updated_at' => now()]);
        } catch (Throwable) {
        }
    }
}
