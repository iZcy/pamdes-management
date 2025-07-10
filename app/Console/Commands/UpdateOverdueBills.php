<?php
// app/Console/Commands/UpdateOverdueBills.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BillingService;

class UpdateOverdueBills extends Command
{
    protected $signature = 'pamdes:update-overdue';
    protected $description = 'Update overdue bill status';

    public function handle(BillingService $billingService)
    {
        $count = $billingService->updateOverdueBills();
        $this->info("Updated {$count} overdue bills");
    }
}
