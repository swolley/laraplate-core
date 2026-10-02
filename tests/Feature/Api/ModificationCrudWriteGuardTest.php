<?php

declare(strict_types=1);

use Modules\Core\Approvals\Operation;
use Modules\Core\Contracts\RestrictsCrudWrites;
use Modules\Core\Models\Modification;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\CrudApiExposure;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function (): void {
    CrudApiExposure::enable();
    $this->user = User::factory()->create();
    $this->user->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));
    $this->actingAs($this->user);
});

it('denies every generic CRUD write on a modification', function (): void {
    expect(new Modification)->toBeInstanceOf(RestrictsCrudWrites::class)
        ->and((new Modification)->deniedCrudWrites())->toContain('insert', 'update', 'delete');
});

it('returns 403 and keeps the quorum when a superadmin changes it through generic CRUD', function (): void {
    $modification = Modification::query()->create([
        'modifiable_type' => User::class,
        'modifiable_id' => $this->user->id,
        'active' => true,
        'operation' => Operation::Update,
        'approvers_required' => 2,
        'disapprovers_required' => 2,
        'md5' => md5('crud-guard'),
        'modifications' => ['name' => ['original' => 'a', 'modified' => 'b']],
    ]);

    $response = $this->putJson(
        route('core.api.replace', ['module' => 'Core', 'entity' => 'modifications', 'id' => $modification->id]),
        ['approvers_required' => 1],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);
    expect($modification->fresh()->approvers_required)->toBe(2);
});
