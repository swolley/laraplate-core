<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Casts\FieldType;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Field;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;

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

it('requires approval for field changes by a user without approve credit', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $field = new Field(['name' => 'field_non_admin', 'type' => FieldType::Text]);
    $permission = PermissionName::forModel($field, 'approve');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with($permission)->andReturn(false);
    Auth::shouldReceive('user')->andReturn($user);

    $method = new ReflectionMethod($field, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($field, ['is_translatable' => true]))->toBeTrue();
});

it('does not require approval for field changes when user has approve credit and N is 1', function (): void {
    App::shouldReceive('runningInConsole')->andReturn(false);

    $field = new Field(['name' => 'field_admin', 'type' => FieldType::Text]);
    $permission = PermissionName::forModel($field, 'approve');

    $user = Mockery::mock(User::class)->makePartial();
    $user->shouldReceive('can')->with($permission)->andReturn(true);
    Auth::shouldReceive('user')->andReturn($user);

    $method = new ReflectionMethod($field, 'requiresApprovalWhen');
    $method->setAccessible(true);

    expect($method->invoke($field, ['is_translatable' => true]))->toBeFalse();
});
