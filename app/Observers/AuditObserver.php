<?php

namespace App\Observers;

use App\Services\Security\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * NOTE: observers are instantiated ONCE per process, so never inject scoped services here
 * (AuditLogger / AuditContext are resolved per call so queue workers never see a stale context).
 *
 * Only model events are audited. Bulk `Model::query()->update()` bypasses events: always mutate via model instances.
 */
class AuditObserver
{
    public function created(Model $m): void
    {
        $this->logger()->recordModel($m, 'created', null, $this->clean($m, $m->getAttributes()));
    }

    public function updated(Model $m): void
    {
        $changes = array_diff_key($m->getChanges(), array_flip($m->auditExcluded()));
        if ($changes === []) {
            return;     // e.g. only updated_at touched
        }
        $old = array_intersect_key($m->getRawOriginal(), $changes);

        $this->logger()->recordModel($m, 'updated', $this->clean($m, $old), $this->clean($m, $changes));
    }

    public function deleted(Model $m): void
    {
        $this->logger()->recordModel($m, 'deleted', $this->clean($m, $m->getAttributes()), null);
    }

    private function logger(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    /** Redact secrets and decode JSON columns so the log is readable and never leaks credentials. */
    private function clean(Model $m, array $attrs): array
    {
        $redact = $m->auditRedacted();
        foreach ($attrs as $k => $v) {
            if (in_array($k, $redact, true)) {
                $attrs[$k] = '[REDACTED]';
            } elseif (is_string($v) && $v !== '' && in_array($v[0], ['{', '['], true)) {
                $d = json_decode($v, true);
                $attrs[$k] = json_last_error() === JSON_ERROR_NONE ? $d : $v;
            }
        }

        return $attrs;
    }
}
