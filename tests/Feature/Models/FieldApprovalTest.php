<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Field;
use Modules\Core\Models\User;

it('opts into the approvals workflow', function (): void {
    expect(class_uses_recursive(Field::class))->toContain(HasApprovals::class);
});

it('persists field changes directly when running in console', function (): void {
    $field = new Field(['name' => 'approval_test_' . uniqid(), 'type' => FieldType::Text, 'options' => (object) []]);
    $field->is_translatable = true;
    $field->save();

    expect($field->exists)->toBeTrue()
        ->and(Field::query()->whereKey($field->getKey())->value('is_translatable'))->toBeTrue();
});

it('requires approval for field changes by a non-admin outside the console', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn(false);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('can')->with('approve.' . (new Field)->getTable())->andReturn(false);
    Auth::shouldReceive('user')->andReturn($user);

    $field = new Field(['name' => 'field_non_admin', 'type' => FieldType::Text]);
    $method = new ReflectionMethod($field, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($field, ['is_translatable' => true]))->toBeTrue();
});

it('does not require approval for field changes by an admin', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('isAdmin')->andReturn(true);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    Auth::shouldReceive('user')->andReturn($user);

    $field = new Field(['name' => 'field_admin', 'type' => FieldType::Text]);
    $method = new ReflectionMethod($field, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($field, ['is_translatable' => true]))->toBeFalse();
});
