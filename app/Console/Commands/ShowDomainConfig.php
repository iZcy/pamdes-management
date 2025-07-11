<?php
// app/Console/Commands/ShowDomainConfig.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Village;

class ShowDomainConfig extends Command
{
    protected $signature = 'pamdes:domains {--check : Check if domains are properly configured}';
    protected $description = 'Show PAMDes domain configuration';

    public function handle()
    {
        if ($this->option('check')) {
            return $this->checkDomainConfig();
        }

        $this->showDomainConfig();
    }

    protected function showDomainConfig()
    {
        $this->info('PAMDes Domain Configuration');
        $this->info('================================');
        $this->newLine();

        // Super Admin Domain
        $superAdminDomain = config('pamdes.domains.super_admin');
        $this->line("<fg=green>Super Admin Domain:</> {$superAdminDomain}");
        $this->line("  - Access: " . (request()->isSecure() ? 'https' : 'http') . "://{$superAdminDomain}/admin");
        $this->newLine();

        // Main Domain
        $mainDomain = config('pamdes.domains.main');
        $this->line("<fg=blue>Main PAMDes Domain:</> {$mainDomain}");
        $this->line("  - Access: " . (request()->isSecure() ? 'https' : 'http') . "://{$mainDomain}");
        $this->newLine();

        // Village Pattern
        $villagePattern = config('pamdes.domains.village_pattern');
        $this->line("<fg=yellow>Village Domain Pattern:</> {$villagePattern}");
        $this->newLine();

        // Active Villages
        $villages = Village::active()->get();
        if ($villages->count() > 0) {
            $this->line("<fg=cyan>Active Village Domains:</>");
            foreach ($villages as $village) {
                $villageDomain = str_replace('{village}', $village->slug, $villagePattern);
                $protocol = request()->isSecure() ? 'https' : 'http';
                $this->line("  - {$village->name}: {$protocol}://{$villageDomain}");
            }
        } else {
            $this->line("<fg=red>No active villages found</>");
        }

        $this->newLine();
        $this->info('To update domains, edit your .env file and update:');
        $this->line('- PAMDES_MAIN_DOMAIN');
        $this->line('- PAMDES_SUPER_ADMIN_DOMAIN');
        $this->line('- PAMDES_VILLAGE_DOMAIN_PATTERN');
    }

    protected function checkDomainConfig()
    {
        $this->info('Checking PAMDes Domain Configuration...');
        $this->newLine();

        $errors = [];

        // Check if domains are configured
        $superAdminDomain = config('pamdes.domains.super_admin');
        $mainDomain = config('pamdes.domains.main');
        $villagePattern = config('pamdes.domains.village_pattern');

        if (empty($superAdminDomain)) {
            $errors[] = 'Super admin domain not configured (PAMDES_SUPER_ADMIN_DOMAIN)';
        }

        if (empty($mainDomain)) {
            $errors[] = 'Main domain not configured (PAMDES_MAIN_DOMAIN)';
        }

        if (empty($villagePattern) || !str_contains($villagePattern, '{village}')) {
            $errors[] = 'Village domain pattern not configured or missing {village} placeholder (PAMDES_VILLAGE_DOMAIN_PATTERN)';
        }

        // Check if domains are using .local (development warning)
        if (str_contains($superAdminDomain, '.local') || str_contains($mainDomain, '.local')) {
            $this->warn('Warning: Using .local domains - this is recommended only for development');
        }

        if (empty($errors)) {
            $this->info('✅ Domain configuration looks good!');
            return 0;
        }

        $this->error('❌ Domain configuration issues found:');
        foreach ($errors as $error) {
            $this->line("  - {$error}");
        }

        $this->newLine();
        $this->info('Please update your .env file with proper domain configuration.');
        return 1;
    }
}
