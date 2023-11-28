<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $queueList = [
            'default',
            'update-all-tenants-settings',
            'workflow',
            'workflow-evaluator',
            'backups',
            'notifications',
            'emails',
            'sms',
            'app-status',
        ];

        $runQueues = implode(',', $queueList);

        $schedule->command("queue:work --queue=$runQueues --tries=2 --stop-when-empty")
            ->withoutOverlapping()
            ->everyMinute()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
