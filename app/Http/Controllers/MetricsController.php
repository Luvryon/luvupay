<?php

namespace App\Http\Controllers;

use App\Services\Ledger\LedgerPoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Prometheus text exposition at /internal/metrics. 404 unless METRICS_TOKEN is set; Bearer token required. */
final class MetricsController
{
    public function __invoke(Request $request, LedgerPoster $poster)
    {
        $token = (string) config('luvupay.observability.metrics_token');
        abort_if($token === '', 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);

        $lines = [];
        $gauge = function (string $name, string $help, array $samples) use (&$lines) {
            $lines[] = "# HELP {$name} {$help}";
            $lines[] = "# TYPE {$name} gauge";
            foreach ($samples as $labels => $v) {
                $lines[] = $name.($labels !== '' ? '{'.$labels.'}' : '').' '.$v;
            }
        };

        try {
            $by = fn (string $table, string $col) => DB::table($table)->selectRaw("{$col} as k, COUNT(*) as c")->groupBy($col)->pluck('c', 'k')->mapWithKeys(fn ($c, $k) => [$col.'="'.$k.'"' => (int) $c])->all();

            $gauge('luvupay_webhook_events', 'Webhook inbox rows by status', $by('webhook_events', 'status'));
            $gauge('luvupay_transactions', 'Transactions by status', $by('transactions', 'status'));
            $gauge('luvupay_failed_jobs', 'Rows in failed_jobs', ['' => (int) DB::table('failed_jobs')->count()]);
            $gauge('luvupay_reconciliation_exceptions_open', 'Open reconciliation exceptions', ['' => (int) DB::table('reconciliation_exceptions')->where('status', 'open')->count()]);
            $unbalanced = collect($poster->trialBalance())->filter(fn ($t) => ! $t['balanced'])->count();
            $gauge('luvupay_ledger_unbalanced_currencies', 'Currencies whose trial balance is off (must be 0)', ['' => $unbalanced]);
        } catch (Throwable $e) {
            abort(503, 'metrics unavailable');
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
