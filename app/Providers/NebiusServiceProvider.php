<?php

namespace App\Providers;

use App\Contracts\NebiusAIContract;
use App\Services\Nebius\NebiusClient;
use Illuminate\Support\ServiceProvider;

class NebiusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NebiusAIContract::class, NebiusClient::class);
    }
}
