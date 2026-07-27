<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Tâches planifiées Operix ─────────────────────────────────────────────────

// Notifications d'expiration : formations, certifs, visites médicales, permis
// Exécuté chaque matin à 7h
Schedule::command('operix:expiry-notifications --days=30')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/expiry-notifications.log'));

// Alerte J-7 supplémentaire
Schedule::command('operix:expiry-notifications --days=7')
    ->dailyAt('07:30')
    ->withoutOverlapping();
