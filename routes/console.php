<?php

use App\Services\ImageStorage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('images:prune {--minutes=60 : Only files older than this are deleted}', function (ImageStorage $images) {
    $count = $images->pruneOrphans((int) $this->option('minutes'));

    $this->info("{$count} fichier(s) orphelin(s) supprimé(s).");
})->purpose('Delete uploaded photos that no product uses');

// Needs the usual cron entry: * * * * * php artisan schedule:run
Schedule::command('images:prune')->dailyAt('03:00');