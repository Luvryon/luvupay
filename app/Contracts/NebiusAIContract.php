<?php

namespace App\Contracts;

interface NebiusAIContract
{
    /**
     * Chat completion with JSON object response.
     *
     * @param  array<int, array{role:string, content:string}>  $messages
     * @return array{content: string, tokens_used: int|null, raw: array}
     */
    public function chatJson(string $featureType, array $messages, ?string $merchantId = null): array;
}
