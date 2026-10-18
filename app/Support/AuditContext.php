<?php

namespace App\Support;

use Closure;

/**
 * Who/where/why for the current unit of work. Registered as SCOPED so it is reset per request AND per queued job.
 * HTTP middleware fills it; jobs/commands declare themselves with runAs().
 */
final class AuditContext
{
    public ?string $actorType = null;     // merchant_api | provider_webhook | job | scheduler | console | system
    public ?string $actorId = null;
    public ?string $requestId = null;
    public ?string $ip = null;
    public ?string $userAgent = null;
    public ?string $idempotencyKey = null;

    public function actor(string $type, ?string $id = null): static
    {
        $this->actorType = $type;
        $this->actorId = $id;

        return $this;
    }

    /** Run a closure (e.g. inside a Job::handle) attributed to a named actor, then restore the previous one. */
    public function runAs(string $type, ?string $id, Closure $callback): mixed
    {
        [$t, $i] = [$this->actorType, $this->actorId];
        $this->actor($type, $id);
        try {
            return $callback();
        } finally {
            $this->actor($t ?? 'system', $i);
        }
    }

    public function actorType(): string
    {
        return $this->actorType ?? (app()->runningInConsole() ? 'console' : 'system');
    }
}
