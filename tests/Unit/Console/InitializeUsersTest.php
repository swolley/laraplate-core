<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Console\InitializeUsers;

it('can initialize users', function (): void {
    /** @var DatabaseManager&Mockery\MockInterface $db */
    $db = Mockery::mock(DatabaseManager::class);
    $command = Mockery::mock(InitializeUsers::class, [$db])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
    $command->shouldReceive('handle')->passthru();

    $groups = collect([
        'superadmin' => Mockery::mock(Role::class),
        'admin' => Mockery::mock(Role::class),
        'guest' => Mockery::mock(Role::class),
    ]);

    $db->shouldReceive('transaction')->once()->andReturnUsing(function ($callback) use ($groups): void {
        $callback($groups['admin']->name, 'root', 'anonymous', 'UserClassMock', $groups);
    });

    $adminUserMock = Mockery::mock('overloads: UserClassMock');

    $userClassMock = Mockery::mock('alias:UserClassMock');
    $userClassMock->shouldReceive('whereName')->twice()->andReturn($userClassMock);
    $userClassMock->shouldReceive('exists')->twice()->andReturn(false, true);
    $userClassMock->shouldReceive('make')->times(3)->andReturn($adminUserMock);

    $command->handle();
});
