<?php

declare(strict_types=1);

use Livewire\Livewire;
use Modules\Core\Filament\Resources\Fields\Pages\CreateField;
use Modules\Core\Models\Field;
use Modules\Core\Tests\Support\HttpContext;

it('does not offer the soft-delete flag, which follows deleted_at', function (): void {
    HttpContext::panelActorWithoutApproval(new Field, ['select', 'insert']);

    Livewire::test(CreateField::class)->assertFormFieldDoesNotExist('is_deleted');
});
