<?php

namespace App\Providers;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Services\Payment\MockPaymentProvider;
use App\Services\Payment\PayPalPaymentProvider;
use App\Services\PayPal\PayPalClient;
use App\Services\PayPal\WebhookHandler;
use Illuminate\Support\ServiceProvider;

// Register in bootstrap/providers.php:  App\Providers\PaymentServiceProvider::class
class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PayPalClient::class, fn () => PayPalClient::fromConfig());
        $this->app->singleton(WebhookHandler::class, fn ($app) => new WebhookHandler($app->make(PayPalClient::class)));

        $this->app->singleton(PaymentProviderInterface::class, fn ($app) => match (config('luvupay.payment.driver')) {
            'mock' => new MockPaymentProvider(),
            default => new PayPalPaymentProvider($app->make(PayPalClient::class), $app->make(WebhookHandler::class)),
        });
    }
}
