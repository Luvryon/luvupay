<?php

namespace App\Traits;

use App\Observers\AuditObserver;

/** `use Auditable;` on a model = every create/update/delete is written to the hash-chained audit log. */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /** Which merchant's chain this row belongs to. Override on Merchant itself. */
    public function auditMerchantId(): ?string
    {
        return $this->getAttribute('merchant_id');
    }

    public function auditExcluded(): array
    {
        return ['updated_at'];
    }

    /** Declare `protected array $auditRedact = ['secret']` on the model. */
    public function auditRedacted(): array
    {
        return property_exists($this, 'auditRedact') ? $this->auditRedact : [];
    }
}
