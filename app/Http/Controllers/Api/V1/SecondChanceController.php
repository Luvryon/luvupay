<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransactionStatus;
use App\Exceptions\SecondChanceException;
use App\Models\Transaction;
use App\Services\Luvu\SecondChanceService;
use App\Support\AuditContext;
use App\Support\HtmlPage;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SecondChanceController
{
    /** POST /api/v1/transactions/{transaction}/second-chance: merchant UI button. (merchant.auth + idempotent) */
    public function store(Request $request, Transaction $transaction, SecondChanceService $svc): JsonResponse
    {
        $data = $request->validate(['merchant_user_id' => 'required|string|max:191']);
        // 404 (not 403) for other merchants' rows: do not reveal that the id exists.
        abort_unless($transaction->merchant_id === $request->attributes->get('merchant_id') && $transaction->merchant_user_id === $data['merchant_user_id'], 404);

        $tx = $svc->trigger($transaction, 'merchant_api');

        return response()->json(['id' => $tx->id, 'status' => $tx->status->value, 'split_minor' => $tx->split_snapshot['amounts_minor'] ?? null], 202);
    }

    /** GET (signed link from the email): confirmation screen. Never moves money on GET (mail scanners prefetch links). */
    public function show(Transaction $transaction, SecondChanceService $svc): Response
    {
        if ($transaction->status !== TransactionStatus::Held || ! $transaction->second_chance_expires_at?->isFuture()) {
            $done = $transaction->second_chance_triggered_at !== null;

            return HtmlPage::render('Second Chance', $done
                ? '<h1>Already done ✓</h1><p>Your Second Chance was used. Your refund is on its way.</p>'
                : '<h1>This window has closed</h1><p>The Second Chance period for this charge is over.</p>');
        }

        $p = $svc->split($transaction->merchant, $transaction->money());
        $fmt = fn (Money $m) => e(number_format($m->amountMinor / 100, 2).' '.$m->currency);
        $body = '<h1>Use your Second Chance</h1><p>Charged by mistake? We will cancel your subscription and split the payment:</p><table>'
            .'<tr><td>Back to you</td><td>'.$fmt($p['user']).'</td></tr><tr><td>Donated to charity</td><td>'.$fmt($p['charity']).'</td></tr>'
            .'<tr><td>Kept by '.e($transaction->merchant->name).'</td><td>'.$fmt($p['merchant']).'</td></tr></table>'
            .'<form method="POST" action="'.e(request()->fullUrl()).'">'.csrf_field().'<button type="submit">Cancel &amp; refund me</button></form>'
            .'<p><small>Offer ends '.e($transaction->second_chance_expires_at->format('M j, H:i T')).'. Protected by LuvuPay.</small></p>';

        return HtmlPage::render('Second Chance', $body);
    }

    /** POST (same signed URL). */
    public function confirm(Transaction $transaction, SecondChanceService $svc, AuditContext $ctx): Response
    {
        $ctx->actor('end_user_link', substr($transaction->id, 0, 8));
        try {
            $svc->trigger($transaction, 'end_user_link');
        } catch (SecondChanceException $e) {
            return HtmlPage::render('Second Chance', '<h1>Sorry</h1><p>'.e($e->getMessage()).'</p>', $e->status);
        }

        return HtmlPage::render('Second Chance', '<h1>Done ✓</h1><p>Your subscription is cancelled and your refund is being processed. You will not be charged again.</p>');
    }
}
