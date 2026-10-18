<?php

namespace App\Providers;

use App\Services\Security\AuditLogger;
use App\Services\Security\WebhookSigner;
use App\Support\AuditContext;
use Illuminate\Support\ServiceProvider;

// Register in bootstrap/providers.php:  App\Providers\SecurityServiceProvider::class
class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped = fresh instance per HTTP request AND per queued job (safe for Octane / long-lived workers)
        $this->app->scoped(AuditContext::class);
        $this->app->scoped(AuditLogger::class);
        $this->app->singleton(WebhookSigner::class);
    }
}
