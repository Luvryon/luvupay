<?php

namespace App\Services\Nebius;

use App\Contracts\NebiusAIContract;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Two-way retention negotiator. Uses Nebius when configured; otherwise a safe local policy engine.
 * Never invents refunds above the safe max; always offers merchant-approved style options.
 */
final class NegotiatorService
{
    public function __construct(
        private readonly RetentionOfferService $offers,
        private readonly ?NebiusAIContract $ai = null,
    ) {}

    /**
     * @param  list<array{role:string,content:string}>  $history
     * @param  array{name?:string,email?:string,amount_minor?:int,currency?:string,product?:string,loyalty_months?:int,fraud_risk?:string}  $ctx
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
            ['id' => 'split_half', 'label' => $split['options'][0]['label']],
            ['id' => 'resale', 'label' => 'List remaining access on marketplace'],
            ['id' => 'safe_refund', 'label' => 'Request safe partial refund (max '.$this->fmt($safe['safe_refund_max_minor'], $currency).')'],
            ['id' => 'pay_full', 'label' => 'Pay full amount now'],
        ];

        $reply = null;
        $thinking = 1800;

        if ($this->aiConfigured()) {
            try {
                $started = microtime(true);
                $messages = array_merge([
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt($name, $product, $amount, $currency, $tone, $safe),
                    ],
                ], $history);
                $result = app(NebiusAIContract::class)->chatText('negotiator', $messages, $ctx['merchant_id'] ?? null);
                $reply = trim((string) ($result['content'] ?? ''));
                $thinking = max(1200, (int) ($result['latency_ms'] ?? 1800));
            } catch (Throwable $e) {
                Log::warning('luvupay.negotiator.ai_fallback', ['error' => $e->getMessage()]);
            }
        }

        if ($reply === null || $reply === '') {
            $reply = $this->localReply($history, $name, $product, $amount, $currency, $tone, $safe);
        }

        // Strip em dash for product copy consistency
        $reply = str_replace(['—', '–'], ['-', '-'], $reply);

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
You are LuvuPay's retention negotiator for {$product}. Customer first name: {$name}. Failed charge: {$amt}.
Tone: {$tone} (empathetic for loyal users, short transactional for new/high-risk).
Rules:
- Offer only: split payment (half now, half in 7 days), marketplace resale of remaining access, safe partial refund up to {$maxRefund}, or pay full now.
- Never promise a full refund above the safe max.
- Keep replies under 80 words. No emojis. No em dash characters.
- Ask one clear question when needed.
PROMPT;
    }

    /** @param list<array{role:string,content:string}> $history */
    private function localReply(array $history, string $name, string $product, int $amount, string $currency, string $tone, array $safe): string
    {
        $lastUser = '';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') === 'user') {
                $lastUser = strtolower((string) $history[$i]['content']);
                break;
            }
        }

        $first = $tone === 'empathetic'
            ? "Hi {$name}, I see your {$product} payment failed. We can fix this without losing access."
            : "Hi {$name}, your {$product} payment did not go through. Here are the options.";

        if ($lastUser === '') {
            return $first.' Would a split payment (half now, half next week), listing remaining access, or a safe partial refund work best?';
        }

        if (str_contains($lastUser, 'disk') || str_contains($lastUser, 'cheap') || str_contains($lastUser, 'bokek') || str_contains($lastUser, 'broke') || str_contains($lastUser, 'half')) {
            $half = $this->fmt((int) floor($amount / 2), $currency);

            return "Understood. I can offer {$half} now and {$half} in 7 days, or list remaining access on the marketplace. Which do you prefer?";
        }
        if (str_contains($lastUser, 'refund')) {
            return 'A full refund may leave the merchant covering processor fees. The safe max refund is '.$this->fmt($safe['safe_refund_max_minor'], $currency).'. Should I request that, or try a split payment instead?';
        }
        if (str_contains($lastUser, 'resale') || str_contains($lastUser, 'jual') || str_contains($lastUser, 'sell')) {
            return 'I can list remaining access on the marketplace so someone else takes over the seat. Confirm and we will open the listing.';
        }
        if (str_contains($lastUser, 'pay') || str_contains($lastUser, 'full')) {
            return 'Alright - choose pay full amount to retry the charge, or split payment if cashflow is tight.';
        }

        return $first.' Reply with split, resale, refund, or pay full.';
    }

    private function fmt(int $minor, string $currency): string
    {
        $n = number_format($minor / 100, 2);

        return $currency === 'USD' ? '$'.$n : $n.' '.$currency;
    }
}
