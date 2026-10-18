<?php

namespace App\Services\Security;

use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes tamper-evident audit rows.
 *
 *  - Atomic with the change: runs inside the caller's DB transaction.
 *  - Hash chain per merchant: hash = HMAC-SHA256(key, prev_hash | canonical row).
 *  - On PostgreSQL, chain writes are serialised with an advisory lock.
 */
class AuditLogger
{
    public function recordModel(Model $model, string $event, ?array $old, ?array $new): void
    {
        $this->record(
            method_exists($model, 'auditMerchantId') ? $model->auditMerchantId() : null,
            $model->getMorphClass(),
            (string) $model->getKey(),
            $event,
            $old,
            $new,
        );
    }

    /** Custom business events, e.g. custom($listing, 'entitlement.sent', ['correlation_id' => ...]). */
    public function custom(Model $subject, string $name, array $data = []): void
    {
        $this->recordModel($subject, 'custom:'.Str::limit($name, 32, ''), null, $data ?: null);
    }

    public function record(?string $merchantId, string $type, string $id, string $event, ?array $old, ?array $new): void
    {
        $ctx = app(AuditContext::class);

        DB::transaction(function () use ($ctx, $merchantId, $type, $id, $event, $old, $new) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('select pg_advisory_xact_lock(hashtextextended(?, 0))', ['luvupay:audit:'.($merchantId ?? 'system')]);
            }

            $head = DB::table('audit_logs');
            $merchantId === null ? $head->whereNull('merchant_id') : $head->where('merchant_id', $merchantId);
            $prev = $head->orderByDesc('id')->value('hash');

            $now = Carbon::now('UTC');

            // Canonical form used for hashing (stable across drivers)
            $canonicalCreatedAt = $now->format('Y-m-d\TH:i:s\Z');

            $fields = [
                'uuid' => (string) Str::uuid(),
                'merchant_id' => $merchantId,
                'auditable_type' => $type,
                'auditable_id' => $id,
                'event' => Str::limit($event, 40, ''),
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'actor_type' => $ctx->actorType(),
                'actor_id' => $ctx->actorId,
                'request_id' => $ctx->requestId,
                'idempotency_key' => $ctx->idempotencyKey,
                'ip_address' => $ctx->ip,
                'user_agent' => $ctx->userAgent ? Str::limit($ctx->userAgent, 512, '') : null,
                'created_at' => $canonicalCreatedAt,
            ];

            // DB column format must be MySQL/MariaDB/SQLite friendly
            $dbCreatedAt = $now->format('Y-m-d H:i:s');

            DB::table('audit_logs')->insert([
                'uuid' => $fields['uuid'],
                'merchant_id' => $fields['merchant_id'],
                'auditable_type' => $fields['auditable_type'],
                'auditable_id' => $fields['auditable_id'],
                'event' => $fields['event'],
                'old_values' => $fields['old_values'] ? json_encode($fields['old_values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'new_values' => $fields['new_values'] ? json_encode($fields['new_values'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'actor_type' => $fields['actor_type'],
                'actor_id' => $fields['actor_id'],
                'request_id' => $fields['request_id'],
                'idempotency_key' => $fields['idempotency_key'],
                'ip_address' => $fields['ip_address'],
                'user_agent' => $fields['user_agent'],
                'created_at' => $dbCreatedAt,
                'prev_hash' => $prev,
                'hash' => $this->hash($fields, $prev),
            ]);
        });
    }

    /** Recompute the hash of a stored row (used by `audit:verify`). */
    public function hashRow(object $row): string
    {
        return $this->hash([
            'uuid' => $row->uuid,
            'merchant_id' => $row->merchant_id,
            'auditable_type' => $row->auditable_type,
            'auditable_id' => $row->auditable_id,
            'event' => $row->event,
            'old_values' => $row->old_values ? json_decode($row->old_values, true) : null,
            'new_values' => $row->new_values ? json_decode($row->new_values, true) : null,
            'actor_type' => $row->actor_type,
            'actor_id' => $row->actor_id,
            'request_id' => $row->request_id,
            'idempotency_key' => $row->idempotency_key,
            'ip_address' => $row->ip_address,
            'user_agent' => $row->user_agent,
            'created_at' => Carbon::parse($row->created_at)->utc()->format('Y-m-d\TH:i:s\Z'),
        ], $row->prev_hash);
    }

    private function hash(array $fields, ?string $prev): string
    {
        $key = config('luvupay.audit.key') ?: config('app.key');
        $json = json_encode($this->canon($fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', ($prev ?? '').'|'.$json, $key);
    }

    private function canon(mixed $v): mixed
    {
        if (! is_array($v)) {
            return $v;
        }
        $v = array_map($this->canon(...), $v);
        if (! array_is_list($v)) {
            ksort($v);
        }

        return $v;
    }
}
