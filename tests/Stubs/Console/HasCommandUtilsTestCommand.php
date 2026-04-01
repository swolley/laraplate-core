<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Console\Command;
use Modules\Core\Helpers\HasCommandUtils;
use ReflectionMethod;

final class HasCommandUtilsTestCommand extends Command
{
    use HasCommandUtils;

    protected $signature = 'test:utils';

    protected $description = 'test';

    public function testValidationCallback(string $attribute, string $value, array $validations): ?string
    {
        $method = new ReflectionMethod($this, 'validationCallback');

        return $method->invoke($this, $attribute, $value, $validations);
    }
}
