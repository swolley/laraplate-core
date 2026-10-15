<?php

declare(strict_types=1);

use Filament\Forms\Components\Select;
use Livewire\Livewire;
use Modules\Core\Filament\Resources\Users\Pages\CreateUser;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Tests\Support\HttpContext;

beforeEach(function (): void {
    HttpContext::panelActorWithoutApproval(new User, ['select', 'insert']);
});

it('labels every role option with its guard, so roles sharing a name are told apart', function (): void {
    $web = Role::query()->create(['name' => 'shared_name', 'guard_name' => 'web']);
    $api = Role::query()->create(['name' => 'shared_name', 'guard_name' => 'api']);

    Livewire::test(CreateUser::class)
        ->assertFormFieldExists('roles', static fn (Select $field): bool => ($field->getOptions()[$web->getKey()] ?? null) === 'shared_name · web'
            && ($field->getOptions()[$api->getKey()] ?? null) === 'shared_name · api');
});
