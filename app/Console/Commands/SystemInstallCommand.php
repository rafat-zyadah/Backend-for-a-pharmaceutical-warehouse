<?php

namespace App\Console\Commands;

use App\Support\Install\InstallStepResult;
use App\Support\Install\SystemInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SystemInstallCommand extends Command
{
    protected $signature = 'system:install';

    protected $description = 'Install mandatory system data (roles, permissions, settings, default supervisor)';

    public function handle(SystemInstaller $installer): int
    {
        if (! Schema::hasTable('users')) {
            $this->components->error('Database is not migrated. Run: php artisan migrate');

            return self::FAILURE;
        }

        $this->components->info('Installing pharmacy warehouse system...');
        $this->newLine();

        foreach ($installer->install() as $result) {
            $this->renderStepResult($result);
        }

        $this->newLine();
        $this->components->info('System installed successfully.');
        $this->renderSupervisorCredentials();

        return self::SUCCESS;
    }

    private function renderStepResult(InstallStepResult $result): void
    {
        $summary = match (true) {
            $result->created > 0 && $result->skipped > 0 => "{$result->created} created, {$result->skipped} skipped",
            $result->created > 0 => "{$result->created} created",
            $result->skipped > 0 => "{$result->skipped} skipped (already present)",
            default => 'no changes',
        };

        $this->components->twoColumnDetail($result->name, $summary);

        foreach ($result->messages as $message) {
            $this->line("  <fg=gray>→ {$message}</>");
        }
    }

    private function renderSupervisorCredentials(): void
    {
        $config = config('pharmacy.default_supervisor');

        $this->newLine();
        $this->components->warn('Default supervisor credentials (change after first login):');
        $this->line("  Username: {$config['username']}");
        $this->line("  Password: {$config['password']}");
        $this->line('  Platform: X-Client-Platform: web');
    }
}
