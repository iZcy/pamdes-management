<?php

// app/Console/Kernel.php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\GenerateBills::class,
        Commands\UpdateOverdueBills::class,
        Commands\SyncVillageData::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Update overdue bills daily
        $schedule->command('pamdes:update-overdue')
            ->daily()
            ->at('00:30');

        // Sync with village system daily
        $schedule->command('pamdes:sync-village')
            ->daily()
            ->at('06:00');

        // Generate bills for active periods weekly
        $schedule->command('pamdes:generate-bills')
            ->weekly()
            ->mondays()
            ->at('08:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
