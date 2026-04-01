<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Console\Command;
use Modules\Core\Helpers\HasCommandModelResolution;

final class HasCommandModelResolutionTestCommand extends Command
{
    use HasCommandModelResolution;

    protected $signature = 'test:resolve {model?} {--model=}';

    protected $description = 'test';

    public function resolveModel(string $optionName = 'model', ?string $namespace = null, bool $required = true): string|false
    {
        return $this->getModelClass($optionName, $namespace, $required);
    }
}
