<?php

use Illuminate\Support\Facades\Schedule;

// Laravel 11 scheduler. Run `php artisan schedule:work` (dev) or a `* * * * * php artisan schedule:run` cron (prod).
Schedule::command('luvi:nudge-scan')->hourly()->withoutOverlapping()->onOneServer();               // Adaptive Nudge: 72h proactive pause
Schedule::command('luvi:settle-expired')->everyFiveMinutes()->withoutOverlapping()->onOneServer(); // safety net for the 48h timers
Schedule::command('resale:maintain')->everyMinute()->withoutOverlapping()->onOneServer();          // release reservations, expire listings
Schedule::command('audit:verify')->dailyAt('02:30')->onOneServer();                                // tamper check (non-zero exit on breakage)
Schedule::command('luvi:reconcile')->dailyAt('02:00')->withoutOverlapping()->onOneServer();

Schedule::command('luvi:apply-pending-webhooks')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('luvi:generate-invoices')->monthlyOn(1, '03:00')->withoutOverlapping()->onOneServer();
Schedule::command('luvi:purge-deactivated')->dailyAt('04:00')->withoutOverlapping()->onOneServer();

// Webhook inbox: sweep events that failed/stuck without a worker picking them up, and prune old rows.
Schedule::command('luvi:webhooks:replay --status=failed,received --stuck-minutes=5 --limit=200')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('luvi:webhooks:replay --prune-days=90')->dailyAt('04:30')->onOneServer();
// Provider/ledger reconciliation also runs shortly after the settlement window for faster discovery of stuck payouts.
Schedule::command('luvi:reconcile')->hourlyAt(20)->withoutOverlapping()->onOneServer();
