<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Livewire\Component;

/**
 * Minimal Livewire host so form schemas can be hydrated and dehydrated in tests
 * without booting a full Filament panel page.
 */
final class SchemaHarnessComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];
}
