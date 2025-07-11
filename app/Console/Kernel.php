<?php
// app/Console/Kernel.php - Updated for independent system

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\GenerateBills::class,
        Commands\UpdateOverdueBills::class,
        Commands\CreateVillage::class, // New command
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Update overdue bills daily
        $schedule->command('pamdes:update-overdue')
            ->daily()
            ->at('00:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Generate local village reports daily (replaces external sync)
        $schedule->command('pamdes:sync-village')
            ->daily()
            ->at('06:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Generate bills for active periods weekly
        $schedule->command('pamdes:generate-bills')
            ->weekly()
            ->mondays()
            ->at('08:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Generate monthly reports (first day of each month)
        $schedule->job(new \App\Jobs\GenerateMonthlyReports())
            ->monthlyOn(1, '09:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Clean up old log files
        $schedule->command('log:clear')
            ->weekly()
            ->saturdays()
            ->at('02:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
