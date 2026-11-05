<?php

namespace App\Contracts;

interface NebiusAIContract
{
    /**
     * Chat completion that must return a JSON object. Throws when the model output is not valid JSON.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @param  array{thinking?:bool, max_tokens?:int}  $options  thinking = ask the endpoint for its native reasoning trace
     * @return array{content: string, data: array<string,mixed>, tokens_used: int|null, model: string|null, latency_ms: int, raw: array, reasoning: string|null, log_id: string|null}
     */
    public function chatJson(string $featureType, array $messages, ?string $merchantId = null, array $options = []): array;

    /**
     * Chat completion that returns plain text (reasoning trace and code fences removed).
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{content: string, tokens_used: int|null, model: string|null, latency_ms: int, raw: array}
     */
    public function chatText(string $featureType, array $messages, ?string $merchantId = null): array;
}
