<?php

use App\Http\Controllers\Api\V1\SecondChanceController;
use App\Http\Controllers\NudgeController;
use Illuminate\Support\Facades\Route;

// In routes/web.php add:  require __DIR__.'/luvupay-public.php';
// Account-less end-user screens: authenticated ONLY by Laravel's signed URLs (tamper-proof, expiring).
Route::middleware(['signed', 'throttle:30,1'])->group(function () {
    Route::get('/second-chance/{transaction}', [SecondChanceController::class, 'show'])->name('second-chance.show');
    Route::post('/second-chance/{transaction}', [SecondChanceController::class, 'confirm'])->name('second-chance.trigger');

    Route::get('/nudge/{subscription}', [NudgeController::class, 'show'])->name('nudge.show');
    Route::post('/nudge/{subscription}', [NudgeController::class, 'confirm'])->name('nudge.confirm');
});
