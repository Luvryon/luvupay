<?php

use App\Http\Controllers\Api\V1\DashboardApiController;
use App\Http\Controllers\Auth\TeamController;
use App\Http\Controllers\Auth\SecurityController;
use App\Http\Controllers\MerchantPanelController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\Demo\DemoCheckoutController;
use App\Http\Controllers\Demo\DemoInsightsController;
use App\Http\Controllers\Demo\DemoLifecycleController;
use App\Http\Controllers\Demo\DemoMonthController;
use App\Http\Controllers\Demo\DemoResaleController;
use App\Http\Controllers\Demo\DemoSmartConvertController;
use App\Http\Controllers\EnterpriseConsoleController;
use Illuminate\Support\Facades\Route;

$console = ['web', 'auth:merchant_web', \App\Http\Middleware\AuthenticateMerchantWeb::class, 'merchant.mfa'];

Route::middleware($console)->group(function () {
    if (is_file(__DIR__.'/onboarding_paypal.php')) {
        require __DIR__.'/onboarding_paypal.php';
    }
    Route::get('/dashboard', [DashboardApiController::class, 'page'])->name('dashboard');
    Route::post('/api/v1/dashboard/project', [DashboardApiController::class, 'setProject'])->middleware('throttle:30,1')->name('dashboard.project.set');
    Route::redirect('/onboarding', '/dashboard')->name('onboarding'); // onboarding removed
    Route::get('/analytics', [MerchantPanelController::class, 'analytics'])->middleware('merchant.can:analytics.read')->name('analytics');
    Route::get('/wallet', [MerchantPanelController::class, 'wallet'])->name('wallet');
    Route::post('/api/v1/dashboard/payouts', [PayoutController::class, 'executePayout'])
        ->middleware(['throttle:10,1'])
        ->name('dashboard.payouts.execute');
    Route::get('/developers', [MerchantPanelController::class, 'developers'])->name('developers');
    Route::get('/subscriptions', [MerchantPanelController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/settings', [MerchantPanelController::class, 'settings'])->middleware('merchant.can:settings.read')->name('settings');

    Route::post('/settings/rotate-key', [MerchantPanelController::class, 'rotateKey'])->middleware('merchant.can:keys.rotate')->name('settings.rotate');
    Route::post('/settings/logo', [MerchantPanelController::class, 'uploadLogo'])->middleware(['merchant.can:settings.read', 'throttle:10,1'])->name('settings.logo');
    Route::post('/settings/logo/remove', [MerchantPanelController::class, 'removeLogo'])->middleware(['merchant.can:settings.read', 'throttle:10,1'])->name('settings.logo.remove');
    Route::post('/settings/payout-recipients', [MerchantPanelController::class, 'requestPayoutRecipient'])->middleware('merchant.can:payouts.write')->name('settings.payout');
    Route::post('/settings/webhook-url', [MerchantPanelController::class, 'requestWebhookUrlChange'])->middleware('merchant.can:webhooks.write')->name('settings.webhook');
    Route::post('/settings/webhook-url/confirm', [MerchantPanelController::class, 'confirmWebhookUrlChange'])->middleware('merchant.can:webhooks.write')->name('settings.webhook.confirm');
    Route::post('/settings/deactivate', [MerchantPanelController::class, 'deactivate'])->middleware('merchant.can:account.deactivate')->name('settings.deactivate');

    Route::get('/export/transactions', [MerchantPanelController::class, 'exportTransactions'])->middleware('merchant.can:exports.read')->name('export.transactions');
    Route::get('/export/audit-logs', [MerchantPanelController::class, 'exportAudit'])->middleware('merchant.can:exports.read')->name('export.audit');

    // Two-factor authentication (every role can protect their own account)
    Route::get('/security', [SecurityController::class, 'index'])->name('security.index');
    Route::post('/security/mfa/start', [SecurityController::class, 'start'])->name('security.mfa.start');
    Route::post('/security/mfa/confirm', [SecurityController::class, 'confirm'])->name('security.mfa.confirm');
    Route::post('/security/mfa/disable', [SecurityController::class, 'disable'])->name('security.mfa.disable');

    // Team & roles
    Route::middleware('merchant.can:team.manage')->prefix('team')->name('team.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])->name('index');
        Route::post('/invitations', [TeamController::class, 'invite'])->middleware('throttle:20,1')->name('invite');
        Route::delete('/invitations/{invitation}', [TeamController::class, 'revoke'])->name('revoke');
        Route::patch('/members/{member}', [TeamController::class, 'updateRole'])->name('role');
        Route::delete('/members/{member}', [TeamController::class, 'remove'])->name('remove');
    });

    Route::prefix('api/v1/dashboard')->name('dashboard.api.')->middleware('throttle:120,1')->group(function () {
        Route::get('summary', [DashboardApiController::class, 'summary'])->name('summary');
        Route::get('analytics', [DashboardApiController::class, 'analytics'])->name('analytics');
        Route::get('project', [DashboardApiController::class, 'project'])->name('project');
        Route::get('transactions', [DashboardApiController::class, 'transactions'])->name('transactions');
        Route::get('subscriptions', [DashboardApiController::class, 'subscriptions'])->name('subscriptions');
        Route::get('resale-listings', [DashboardApiController::class, 'resaleListings'])->name('resale');
        Route::get('audit-logs', [DashboardApiController::class, 'auditLogs'])->middleware('merchant.can:exports.read')->name('audit');
        Route::post('wallet/withdraw', [\App\Http\Controllers\PayoutController::class, 'executePayout'])->middleware('throttle:10,1')->name('wallet.withdraw');
    });


    // Enterprise: invoices, webhook delivery center, API usage meter
    Route::get('/dashboard/invoices/{id}', [EnterpriseConsoleController::class, 'invoiceShow'])->name('dashboard.invoice');
    Route::prefix('api/v1/dashboard')->name('dashboard.ent.')->middleware('throttle:120,1')->group(function () {
        Route::get('usage', [EnterpriseConsoleController::class, 'apiUsage'])->name('usage');
        Route::get('invoices/{id}', [EnterpriseConsoleController::class, 'invoiceJson'])->name('invoice.json');
        Route::get('webhook-deliveries', [EnterpriseConsoleController::class, 'webhookDeliveries'])->name('deliveries');
        Route::post('webhook-deliveries/{id}/retry', [EnterpriseConsoleController::class, 'retryWebhookDelivery'])->name('deliveries.retry');
    });
    Route::prefix('api/v1/dashboard/demo')->middleware(['demo.simulator', 'throttle:30,1'])->group(function () {
        Route::post('rate-limit/max', [EnterpriseConsoleController::class, 'simulateRateLimit'])->name('demo.ratelimit');
        Route::post('rate-limit/reset', [EnterpriseConsoleController::class, 'resetRateLimit'])->name('demo.ratelimit.reset');
    });

    // Demo Simulator (hackathon / local only — gated by demo.simulator middleware)
    Route::get('/dashboard/paypal/return', [DemoCheckoutController::class, 'paypalReturn'])->middleware('demo.simulator')->name('dashboard.paypal.return');
    Route::get('/dashboard/paypal/cancel', [DemoCheckoutController::class, 'paypalCancel'])->middleware('demo.simulator')->name('dashboard.paypal.cancel');

    Route::prefix('api/v1/dashboard/demo')->name('dashboard.demo.')->middleware(['demo.simulator', 'throttle:30,1'])->group(function () {
        Route::post('checkout', [DemoCheckoutController::class, 'simulateCheckout'])->name('checkout');
        Route::post('simulate-month', DemoMonthController::class)->name('simulate-month');
        Route::post('forgot', [DemoLifecycleController::class, 'simulateForgot'])->name('forgot');
        Route::post('decision', [DemoLifecycleController::class, 'decision'])->name('decision');
        Route::post('resale', [DemoResaleController::class, 'simulateResale'])->name('resale');
        Route::post('smart-convert', [DemoSmartConvertController::class, 'smartConvert'])->name('smart-convert');
        Route::get('webhook-logs', [DemoInsightsController::class, 'webhookLogs'])->name('webhooks');
        Route::get('receipts/{id}', [DemoInsightsController::class, 'charityReceipt'])->name('receipt');
    });

});

// Invitation acceptance: public, authenticated by an unguessable single-use token.
Route::middleware(['web', 'guest:merchant_web'])->group(function () {
    Route::get('/team/accept/{token}', [TeamController::class, 'showAccept'])->name('team.accept');
    Route::post('/team/accept/{token}', [TeamController::class, 'accept'])->middleware('throttle:10,1')->name('team.accept.store');
});

Route::get('/internal/metrics', \App\Http\Controllers\MetricsController::class)->middleware('throttle:60,1')->name('metrics');
Route::get('/internal/health', \App\Http\Controllers\Api\V1\HealthController::class)->middleware('throttle:60,1')->name('internal.health');
