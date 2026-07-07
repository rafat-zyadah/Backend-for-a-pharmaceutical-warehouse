<?php

namespace App\Support\Install\Contracts;

use App\Support\Install\InstallStepResult;

interface InstallStep
{
    public function name(): string;

    public function run(): InstallStepResult;
}
