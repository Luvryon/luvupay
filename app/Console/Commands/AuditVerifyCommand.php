<?php

namespace App\Console\Commands;

use App\Services\Security\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditVerifyCommand extends Command
{
    protected $signature = 'audit:verify {--merchant= : Verify only this merchant\'s chain}';
    protected $description = 'Re-computes the audit_logs hash chain and reports the first tampered or missing row.';

    public function handle(AuditLogger $audit): int
    {
        $q = DB::table('audit_logs');
        if ($m = $this->option('merchant')) {
            $q->where('merchant_id', $m);
        }

        $prev = [];
        $count = 0;
        foreach ($q->lazyById(1000, 'id') as $row) {
            $scope = $row->merchant_id ?? 'system';

            if ($row->prev_hash !== ($prev[$scope] ?? null)) {
                $this->error("CHAIN BROKEN at audit_logs.id={$row->id} (scope {$scope}): a preceding row was removed, inserted or reordered.");
                return self::FAILURE;
            }
            if (! hash_equals($audit->hashRow($row), $row->hash)) {
                $this->error("TAMPERED ROW at audit_logs.id={$row->id} (scope {$scope}): contents do not match their hash.");
                return self::FAILURE;
            }

            $prev[$scope] = $row->hash;
            $count++;
        }

        $this->info("OK: {$count} audit rows verified across ".count($prev).' chain(s).');

        return self::SUCCESS;
    }
}
