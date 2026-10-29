<?php

declare(strict_types=1);

namespace LaIOT\Services\Systemd;

interface ProcessRunner
{
    /**
     * @param list<string> $command
     */
    public function run(array $command, int $timeout = 20): ProcessResult;
}
