<?php

namespace App\Support\Install\Steps;

use App\Models\ApplicationSetting;
use App\Support\Install\Contracts\InstallStep;
use App\Support\Install\InstallStepResult;

class ApplicationSettingsInstallStep implements InstallStep
{
    public function name(): string
    {
        return 'Application Settings';
    }

    public function run(): InstallStepResult
    {
        $created = 0;
        $skipped = 0;

        foreach (config('install.settings', []) as $key => $definition) {
            $exists = ApplicationSetting::query()->where('key', $key)->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            ApplicationSetting::query()->create([
                'key' => $key,
                'value' => (string) ($definition['value'] ?? ''),
                'type' => (string) ($definition['type'] ?? 'string'),
            ]);
            $created++;
        }

        return new InstallStepResult(
            name: $this->name(),
            created: $created,
            skipped: $skipped,
        );
    }
}
