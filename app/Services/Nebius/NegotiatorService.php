<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-way retention negotiator. Option clicks get deterministic replies;
 * free-text can use Nebius when configured.
 */
final class NegotiatorService
{
    public function __construct(
        private readonly RetentionOfferService $offers,
        private readonly ?NebiusAIContract $ai = null,
    ) {}

    /**
     * @param  list<array{role:string,content:string}>  $history
     * @param  array{name?:string,email?:string,amount_minor?:int,currency?:string,product?:string,loyalty_months?:int,fraud_risk?:string,merchant_id?:string}  $ctx
     * @return array{reply:string,options:list<array{id:string,label:string}>,tone:string,safe_refund:array,thinking_ms:int}
     */
    public function turn(array $history, array $ctx): array
    {
        $amount = max(0, (int) ($ctx['amount_minor'] ?? 2000));
        $currency = (string) ($ctx['currency'] ?? 'USD');
        $name = (string) ($ctx['name'] ?? 'there');
        $product = (string) ($ctx['product'] ?? 'subscription');
        $tone = $this->offers->toneFor($ctx);
        $safe = $this->offers->safeRefundMax($amount);
        $split = $this->offers->microPaymentSplit($amount, $currency);

        $options = [
            ['id' => 'split_half', 'label' => $split['options'][0]['label'] ?? 'Pay half now, half next week'],
            ['id' => 'resale', 'label' => 'List remaining access on marketplace'],
            ['id' => 'safe_refund', 'label' => 'Request safe partial refund (max '.$this->fmt($safe['safe_refund_max_minor'], $currency).')'],
            ['id' => 'pay_full', 'label' => 'Pay full amount now'],
        ];

        $lastUser = $this->lastUserText($history);
        $optionId = $this->detectOptionId($lastUser, $options);

        // Option buttons → always deterministic (never generic loop)
        if ($optionId !== null || $lastUser === '') {
            $reply = $this->localReply($lastUser, $optionId, $name, $product, $amount, $currency, $tone, $safe, $split);
            $thinking = 400;

            return [
                'reply' => $reply,
                'options' => $options,
                'tone' => $tone,
                'safe_refund' => $safe,
                'thinking_ms' => $thinking,
            ];
        }

        $reply = null;
        $thinking = 1200;
        if ($this->aiConfigured()) {
            try {
                $messages = array_merge([
                    ['role' => 'system', 'content' => $this->systemPrompt($name, $product, $amount, $currency, $tone, $safe)],
                ], $history);
                $result = app(NebiusAIContract::class)->chatText('negotiator', $messages, $ctx['merchant_id'] ?? null);
                $reply = trim((string) ($result['content'] ?? ''));
                $thinking = max(600, (int) ($result['latency_ms'] ?? 1200));
            } catch (Throwable $e) {
                Log::warning('luvupay.negotiator_ai_failed', ['err' => $e->getMessage()]);
            }
        }
        if ($reply === null || $reply === '') {
            $reply = $this->localReply($lastUser, null, $name, $product, $amount, $currency, $tone, $safe, $split);
            $thinking = 400;
        }

        return [
            'reply' => $reply,
            'options' => $options,
            'tone' => $tone,
            'safe_refund' => $safe,
            'thinking_ms' => $thinking,
        ];
    }

    private function aiConfigured(): bool
    {
        try {
            return filled(config('nebius.api_key')) || filled(env('NEBIUS_API_KEY'));
        } catch (Throwable) {
            return false;
        }
    }

    private function systemPrompt(string $name, string $product, int $amount, string $currency, string $tone, array $safe): string
    {
        $amt = $this->fmt($amount, $currency);
        $maxRefund = $this->fmt($safe['safe_refund_max_minor'], $currency);

        return <<<PROMPT
You are LuvuPay's retention negotiator for {$product}. Customer: {$name}. Failed charge: {$amt}.
Tone: {$tone}.
Rules:
- Respond specifically to what the user just said. Never repeat the same opening line.
- Offer only: split payment, marketplace resale, safe partial refund up to {$maxRefund}, or pay full now.
- Never promise a full refund above the safe max.
- Keep replies under 80 words. No emojis. No em dash characters.
PROMPT;
    }

    /** @param  list<array{role:string,content:string}>  $history */
    private function lastUserText(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                return strtolower(trim((string) $history[$i]['content']));
            }
        }

        return '';
    }

    /**
     * @param  list<array{id:string,label:string}>  $options
     */
    private function detectOptionId(string $lastUser, array $options): ?string
    {
        if ($lastUser === '') {
            return null;
        }
        if (preg_match('/\[option:([a-z_]+)\]/', $lastUser, $m)) {
            return $m[1];
        }
        foreach ($options as $opt) {
            $id = strtolower((string) ($opt['id'] ?? ''));
            if ($id !== '' && (str_contains($lastUser, $id) || $lastUser === str_replace('_', ' ', $id))) {
                return $id;
            }
        }
        if (str_contains($lastUser, 'split') || str_contains($lastUser, 'half') || str_contains($lastUser, 'minggu')) {
            return 'split_half';
        }
        if (str_contains($lastUser, 'resale') || str_contains($lastUser, 'marketplace') || str_contains($lastUser, 'jual')) {
            return 'resale';
        }
        if (str_contains($lastUser, 'refund')) {
            return 'safe_refund';
        }
        if (str_contains($lastUser, 'pay full') || str_contains($lastUser, 'full amount') || $lastUser === 'pay_full') {
            return 'pay_full';
        }

        return null;
    }

    private function localReply(
        string $lastUser,
        ?string $optionId,
        string $name,
        string $product,
        int $amount,
        string $currency,
        string $tone,
        array $safe,
        array $split,
    ): string {
        $half = $this->fmt((int) floor($amount / 2), $currency);
        $maxRefund = $this->fmt($safe['safe_refund_max_minor'], $currency);
        $splitLabel = $split['options'][0]['label'] ?? "{$half} now and {$half} in 7 days";

        if ($optionId === null && $lastUser === '') {
            return $tone === 'empathetic'
                ? "Hi {$name}, I see your {$product} payment failed. We can fix this without losing access. Would split pay, resale, or a safe partial refund work best?"
                : "Hi {$name}, your {$product} payment did not go through. Choose an option below: split, resale, safe refund, or pay full.";
        }

        return match ($optionId) {
            'split_half' => "Great choice. I locked in a micro-payment plan: {$splitLabel}. Confirm and we will schedule the second charge in 7 days while access stays active.",
            'resale' => 'Confirmed. I can list remaining access on the marketplace so another buyer takes the seat. The original charge stays settled for the merchant when it sells.',
            'safe_refund' => "Understood. The safe partial refund cap is {$maxRefund} (processor fees protected). I will not exceed that amount. Confirm to request this refund path.",
            'pay_full' => 'OK — we will retry the full amount now. If the card still fails, come back and pick split pay or safe refund instead.',
            default => $this->freeTextFallback($lastUser, $name, $product, $half, $maxRefund),
        };
    }

    private function freeTextFallback(string $lastUser, string $name, string $product, string $half, string $maxRefund): string
    {
        if (str_contains($lastUser, 'bokek') || str_contains($lastUser, 'broke') || str_contains($lastUser, 'disk')) {
            return "I hear you, {$name}. Cash is tight — {$half} now and {$half} next week keeps {$product} active without a full charge today.";
        }

        return "Thanks for the detail. For {$product}, I can still offer split ({$half}+{$half}), marketplace resale, safe refund up to {$maxRefund}, or pay full. Which path should we take?";
    }

    private function fmt(int $minor, string $currency): string
    {
        $n = number_format($minor / 100, 2);

        return $currency === 'USD' ? '$'.$n : $n.' '.$currency;
    }
}
