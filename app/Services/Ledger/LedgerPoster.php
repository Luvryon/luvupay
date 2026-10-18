<?php

namespace App\Services\Ledger;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\LedgerException;
use App\Models\Ledger\LedgerAccount;
use App\Models\Ledger\LedgerEntry;
use App\Models\Ledger\LedgerJournal;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry posting. Chart of accounts (all in the minor unit of the journal currency):
 *
 *   asset:paypal_clearing                funds we hold at the provider
 *   liability:merchant_held:<id>         customer money inside the Second Chance window
 *   liability:merchant_payable:<id>      owed to the merchant
 *   liability:user_refund_payable        owed back to subscribers
 *   liability:charity_payable            owed to charity wallets
 *   liability:seller_payable             owed to resale sellers
 *   revenue:platform_fees                LuvuPay fees
 *
 * Rules: every journal balances; a reference is posted at most once (idempotent); rows are never mutated.
 * Call inside the same DB transaction as the business change so both commit or both roll back.
 */
class LedgerPoster
{
    public const CLEARING = 'asset:paypal_clearing';
    public const USER_REFUND = 'liability:user_refund_payable';
    public const CHARITY = 'liability:charity_payable';
    public const SELLER = 'liability:seller_payable';
    public const FEES = 'revenue:platform_fees';

    public static function held(string $merchantId): string { return "liability:merchant_held:{$merchantId}"; }
    public static function payable(string $merchantId): string { return "liability:merchant_payable:{$merchantId}"; }

    /**
     * @param  list<array{0:string,1:'debit'|'credit',2:int}>  $lines  [accountCode, direction, amountMinor]
     */
    public function post(string $reference, string $kind, string $currency, array $lines, ?string $merchantId = null, ?string $transactionId = null, array $metadata = []): LedgerJournal
    {
        if ($existing = LedgerJournal::query()->where('reference', $reference)->first()) {
            return $existing;                                   // exactly-once
        }

        $lines = array_values(array_filter($lines, fn ($l) => (int) $l[2] > 0));
        if (count($lines) < 2) {
            throw new LedgerException("Journal {$reference} needs at least two non-zero lines.");
        }
        $debit = array_sum(array_map(fn ($l) => $l[1] === 'debit' ? (int) $l[2] : 0, $lines));
        $credit = array_sum(array_map(fn ($l) => $l[1] === 'credit' ? (int) $l[2] : 0, $lines));
        if ($debit !== $credit) {
            throw new LedgerException("Journal {$reference} is unbalanced: debit {$debit} != credit {$credit}.");
        }

        try {
            return DB::transaction(function () use ($reference, $kind, $currency, $lines, $merchantId, $transactionId, $metadata) {
                $now = now();
                $journal = LedgerJournal::create([
                    'reference' => $reference, 'kind' => $kind, 'merchant_id' => $merchantId, 'transaction_id' => $transactionId,
                    'currency' => $currency, 'metadata' => $metadata ?: null, 'posted_at' => $now, 'created_at' => $now,
                ]);
                foreach ($lines as [$code, $direction, $amount]) {
                    LedgerEntry::create([
                        'journal_id' => $journal->id, 'account_id' => $this->account($code, $currency, $merchantId)->id,
                        'direction' => $direction, 'amount_minor' => (int) $amount, 'currency' => $currency, 'created_at' => $now,
                    ]);
                }

                return $journal;
            });
        } catch (UniqueConstraintViolationException) {
            return LedgerJournal::query()->where('reference', $reference)->firstOrFail();   // lost a race: same result
        }
    }

    private function account(string $code, string $currency, ?string $merchantId): LedgerAccount
    {
        $type = explode(':', $code, 2)[0];
        if (! in_array($type, ['asset', 'liability', 'revenue', 'expense'], true)) {
            throw new LedgerException("Unknown account type for {$code}.");
        }

        return LedgerAccount::query()->firstOrCreate(
            ['code' => $code, 'currency' => $currency],
            ['type' => $type, 'merchant_id' => str_contains($code, 'merchant_') ? $merchantId : null],
        );
    }

    // ------------------------------------------------------------------ domain postings

    /** A subscription charge was captured by the provider. */
    public function chargeCaptured(Transaction $charge): LedgerJournal
    {
        $m = (string) $charge->merchant_id;
        $to = $charge->status === TransactionStatus::Held ? self::held($m) : self::payable($m);

        return $this->post("charge:{$charge->id}", 'charge_captured', $charge->currency, [
            [self::CLEARING, 'debit', $charge->amount_minor],
            [$to, 'credit', $charge->amount_minor],
        ], $m, $charge->id);
    }

    /** Second Chance triggered: held funds are split. $parts = Money for user|charity|merchant|luvi_fee. */
    public function splitCommitted(Transaction $charge, array $parts): LedgerJournal
    {
        $m = (string) $charge->merchant_id;
        $amt = fn (string $k) => isset($parts[$k]) && $parts[$k] instanceof Money ? $parts[$k]->amountMinor : 0;

        return $this->post("split:{$charge->id}", 'second_chance_split', $charge->currency, [
            [self::held($m), 'debit', $charge->amount_minor],
            [self::USER_REFUND, 'credit', $amt('user')],
            [self::CHARITY, 'credit', $amt('charity')],
            [self::payable($m), 'credit', $amt('merchant')],
            [self::FEES, 'credit', $amt('luvi_fee')],
        ], $m, $charge->id);
    }

    /** Window expired without a Second Chance: merchant keeps 100%. */
    public function chargeSettled(Transaction $charge): LedgerJournal
    {
        $m = (string) $charge->merchant_id;

        return $this->post("settle:{$charge->id}", 'second_chance_expired', $charge->currency, [
            [self::held($m), 'debit', $charge->amount_minor],
            [self::payable($m), 'credit', $charge->amount_minor],
        ], $m, $charge->id);
    }

    /** Resale buyer payment captured: seller share + platform fee. */
    public function resaleCaptured(Transaction $sale, Money $seller, Money $platform): LedgerJournal
    {
        return $this->post("resale:{$sale->id}", 'resale_captured', $sale->currency, [
            [self::CLEARING, 'debit', $sale->amount_minor],
            [self::SELLER, 'credit', $seller->amountMinor],
            [self::FEES, 'credit', $platform->amountMinor],
        ], (string) $sale->merchant_id, $sale->id);
    }

    /** Money actually left via refund / payout. Internal retention rows move no cash and post nothing. */
    public function payoutCompleted(Transaction $tx): ?LedgerJournal
    {
        $liability = match ($tx->type) {
            TransactionType::Refund => self::USER_REFUND,
            TransactionType::CharityPayout => self::CHARITY,
            TransactionType::ResalePayout => self::SELLER,
            default => null,
        };
        if ($liability === null) {
            return null;
        }

        return $this->post("payout:{$tx->id}", 'payout_completed', $tx->currency, [
            [$liability, 'debit', $tx->amount_minor],
            [self::CLEARING, 'credit', $tx->amount_minor],
        ], (string) $tx->merchant_id, $tx->id);
    }

    // ------------------------------------------------------------------ integrity

    /** Global trial balance: total debits must equal total credits, per currency. */
    public function trialBalance(): array
    {
        $rows = LedgerEntry::query()->selectRaw("currency, direction, SUM(amount_minor) as total")->groupBy('currency', 'direction')->get();
        $out = [];
        foreach ($rows as $r) {
            $out[$r->currency][$r->direction] = (int) $r->total;
        }
        foreach ($out as $cur => &$t) {
            $t += ['debit' => 0, 'credit' => 0];
            $t['balanced'] = $t['debit'] === $t['credit'];
        }

        return $out;
    }

    /** debit-positive balance of one account. */
    public function balance(string $code, string $currency): int
    {
        $acc = LedgerAccount::query()->where(compact('code', 'currency'))->first();
        if (! $acc) {
            return 0;
        }
        $d = (int) LedgerEntry::query()->where('account_id', $acc->id)->where('direction', 'debit')->sum('amount_minor');
        $c = (int) LedgerEntry::query()->where('account_id', $acc->id)->where('direction', 'credit')->sum('amount_minor');

        return $d - $c;
    }
}
