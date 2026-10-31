<?php

use App\Http\Controllers\Auth\MerchantAuthController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\MerchantPanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'));
Route::get('/invoice/{id}', [\App\Http\Controllers\InvoiceController::class, 'publicShow'])->middleware(['signed', 'throttle:60,1,web10'])->name('invoice.public');
Route::get('/invoice/{id}/pdf', [\App\Http\Controllers\InvoiceController::class, 'publicPdf'])->middleware(['signed', 'throttle:60,1,web11'])->name('invoice.public.pdf');
Route::get('/brand/logo/{id}', [\App\Http\Controllers\BrandController::class, 'logo'])->middleware('throttle:120,1,web12')->name('brand.logo');

// Public storefront + hosted checkout + smart-token redemption
Route::get('/m/{merchant}', [\App\Http\Controllers\HostedController::class, 'storefront'])->middleware('throttle:120,1,web15')->name('store');
Route::get('/m/{merchant}/listings', [\App\Http\Controllers\HostedController::class, 'listings'])->middleware('throttle:120,1,web15b')->name('store.listings');
Route::get('/checkout/{merchant}', [\App\Http\Controllers\HostedController::class, 'checkout'])->middleware('throttle:120,1,web16')->name('hosted.checkout');
Route::post('/checkout/{merchant}/pay', [\App\Http\Controllers\HostedController::class, 'payCheckout'])->middleware('throttle:10,1,web17')->name('hosted.checkout.pay');
Route::get('/redeem/{token}', [\App\Http\Controllers\HostedController::class, 'redeem'])->middleware('throttle:120,1,web18')->name('hosted.redeem');
Route::post('/redeem/{token}/pay', [\App\Http\Controllers\HostedController::class, 'payRedeem'])->middleware('throttle:10,1,web19')->name('hosted.redeem.pay');
Route::get('/pay/return', [\App\Http\Controllers\HostedController::class, 'paypalReturn'])->middleware('throttle:30,1,payreturn')->name('hosted.paypal.return');
Route::get('/pay/cancel', [\App\Http\Controllers\HostedController::class, 'paypalCancel'])->middleware('throttle:30,1,paycancel')->name('hosted.paypal.cancel');
Route::get('/marketplace', fn () => view('marketplace'))->name('marketplace');
Route::get('/api/marketplace/listings', [MarketplaceController::class, 'index']);
Route::post('/api/marketplace/sell', [MarketplaceController::class, 'sell'])->middleware('throttle:10,1,web24');
Route::post('/api/marketplace/listings/{id}/buy', [MarketplaceController::class, 'buy'])->middleware('throttle:20,1,web25');
Route::get('/marketplace/listings/{id}/verify', [MarketplaceController::class, 'verify'])->middleware(['signed', 'throttle:30,1,web26'])->name('marketplace.verify');
Route::get('/marketplace/paypal/return', [MarketplaceController::class, 'paypalReturn'])->name('marketplace.paypal.return');
Route::get('/marketplace/paypal/cancel', [MarketplaceController::class, 'paypalCancel'])->name('marketplace.paypal.cancel');

Route::middleware('guest:merchant_web')->group(function () {
    Route::get('/login', [MerchantAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [MerchantAuthController::class, 'login'])->middleware('throttle:20,1,web32');
    Route::get('/register', [MerchantAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [MerchantAuthController::class, 'register'])->middleware('throttle:10,1,web34');

    // Second step of login when the account has two-factor authentication
    Route::get('/mfa/challenge', [MerchantAuthController::class, 'showMfa'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MerchantAuthController::class, 'verifyMfa'])->middleware('throttle:10,1,web38')->name('mfa.verify');
});

Route::post('/logout', [MerchantAuthController::class, 'logout'])->middleware('auth:merchant_web')->name('logout');

Route::middleware('auth:merchant_web')->group(function () {
    Route::get('/email/verify', [MerchantAuthController::class, 'showVerify'])->name('verification.notice');
    Route::post('/email/verify', [MerchantAuthController::class, 'verify'])->name('verification.verify');
    Route::post('/email/verify/resend', [MerchantAuthController::class, 'resend'])->name('verification.resend');
});

Route::get('/docs', [DocsController::class, 'index'])->name('docs');
Route::get('/docs/api', [DocsController::class, 'api'])->name('docs.api');
Route::get('/docs/webhooks', [DocsController::class, 'webhooks'])->name('docs.webhooks');
Route::get('/docs/postman', [DocsController::class, 'postman'])->name('docs.postman');
Route::get('/docs/cookbook', [DocsController::class, 'cookbook'])->name('docs.cookbook');
Route::get('/docs/errors', [DocsController::class, 'errors'])->name('docs.errors');
Route::get('/docs/octane', [DocsController::class, 'octane'])->name('docs.octane');
Route::get('/docs/security', fn () => view('docs.security'))->name('docs.security');

Route::get('/directory', function () {
    $merchants = \App\Models\Merchant::query()
        ->where('show_in_ethical_directory', true)
        ->where('is_active', true)
        ->orderBy('name')
        ->get(['id', 'name', 'subscription_tier']);
    return view('directory', compact('merchants'));
})->name('directory');

Route::get('/docs/legal', fn () => view('docs.legal'))->name('docs.legal');

Route::get('/docs/resale-money', fn () => view('docs.resale-money'))->name('docs.resale-money');

Route::get('/docs/ai', fn () => view('docs.ai'))->name('docs.ai');
Route::get('/docs/sandbox', fn () => view('docs.sandbox'))->name('docs.sandbox');

Route::get('/status', fn () => view('status.page'))->name('status');

Route::get('/privacy', fn () => view('legal.privacy'))->name('privacy');
Route::get('/terms', fn () => view('legal.terms'))->name('terms');
Route::get('/support', fn () => view('support'))->name('support');
