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
