<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Import;

use Modules\Core\Console\AbstractImportCommand;
use Override;

final class TestImportCommand extends AbstractImportCommand
{
    #[Override]
    protected $name = 'test:import';

    #[Override]
    protected $description = 'Test module import <fg=green>(Modules\\Test)</fg=green>';
}
