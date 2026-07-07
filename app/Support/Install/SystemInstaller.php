<?php

namespace App\Support\Install;

use App\Support\Install\Contracts\InstallStep;
use App\Support\Install\Steps\ApplicationSettingsInstallStep;
use App\Support\Install\Steps\DefaultSupervisorInstallStep;
use App\Support\Install\Steps\RolesAndPermissionsInstallStep;

class SystemInstaller
{
    /** @var list<InstallStep> */
    private array $steps;

    public function __construct()
    {
        $this->steps = [
            new RolesAndPermissionsInstallStep,
            new ApplicationSettingsInstallStep,
            new DefaultSupervisorInstallStep,
        ];
    }

    /**
     * @return list<InstallStepResult>
     */
    public function install(): array
    {
        $results = [];

        foreach ($this->steps as $step) {
            $results[] = $step->run();
        }

        return $results;
    }
}
