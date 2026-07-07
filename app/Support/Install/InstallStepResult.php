<?php

namespace App\Support\Install;

readonly class InstallStepResult
{
    /**
     * @param  list<string>  $messages
     */
    public function __construct(
        public string $name,
        public int $created = 0,
        public int $skipped = 0,
        public array $messages = [],
    ) {}

    public function isSuccessful(): bool
    {
        return true;
    }
}
