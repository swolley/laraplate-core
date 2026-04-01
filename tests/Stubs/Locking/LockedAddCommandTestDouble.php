<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Locking;

use Modules\Core\Locking\Console\LockedAddCommand;

final class LockedAddCommandTestDouble extends LockedAddCommand
{
    public string $argModel = 'MissingModel';

    public ?string $argNamespace = null;

    public array $errors = [];

    public array $infos = [];

    public ?string $stubPath = null;

    public function argument($key = null): mixed
    {
        return $this->argModel;
    }

    public function option($key = null): mixed
    {
        return $this->argNamespace;
    }

    public function error($string, $verbosity = null): void
    {
        $this->errors[] = (string) $string;
    }

    public function info($string, $verbosity = null): void
    {
        $this->infos[] = (string) $string;
    }

    public function getStubPath(): string
    {
        return $this->stubPath ?? parent::getStubPath();
    }
}
