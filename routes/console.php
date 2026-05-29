<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('authority-approvals:check-escalations', function () {
    $count = app(\App\Services\AuthorityEscalationService::class)->escalateOverdueApprovals();

    $this->info("Escalated {$count} overdue authority approvals.");
})->purpose('Escalate overdue authority approvals based on configured authority response windows.');

Schedule::command('authority-approvals:check-escalations')->hourly();
