<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Module projets : clôture des collectes échues (échec + remboursements) et priorisation, chaque nuit
Schedule::command('projects:close-expired')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('projects:score')->dailyAt('02:00')->withoutOverlapping();

// Module incidents : délais dépassés (SLA) toutes les 15 minutes, incidents liés toutes les 30 minutes
Schedule::command('incidents:check-sla')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('incidents:cluster')->everyThirtyMinutes()->withoutOverlapping();

// Module infrastructures : score de risque chaque nuit (après les incidents liés), inspections en retard chaque matin
Schedule::command('infrastructures:score')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('infrastructures:check-inspections')->dailyAt('07:00')->withoutOverlapping();
