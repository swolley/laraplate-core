<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Console\Command;
use Modules\Core\Helpers\HasCommandModelResolution;

final class HasCommandModelResolutionOptionOnlyTestCommand extends Command
{
    use HasCommandModelResolution;

    protected $signature = 'test:resolve-option {--entity=}';

    protected $description = 'test';

    public function resolveModel(string $optionName = 'entity', ?string $namespace = null, bool $required = true): string|false
    {
        return $this->getModelClass($optionName, $namespace, $required);
    }
}
