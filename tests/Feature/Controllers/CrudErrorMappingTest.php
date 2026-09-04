<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\CrudService;
use Symfony\Component\HttpFoundation\Response;

/**
 * The CRUD controller maps each failure onto a status. These cases used to share one
 * arm answering 304, which carries no body by specification, so the reason for the
 * refusal never reached the caller.
 */
function errorMappingActor(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('superadmin', 'web'));

    test()->actingAs($actor);

    return $actor;
}

it('answers 400 with the reason when the criteria name something that is not a relation', function (): void {
    errorMappingActor();
    Setting::factory()->persistedWithoutApprovalCapture()->create();

    // QueryBuilder::assertAggregateRelation raises this for an aggregate over a name
    // that is not an Eloquent relation. It is an InvalidArgumentException, and so a
    // LogicException, which is how it used to reach the 304 arm and lose its message.
    $service = Mockery::mock(CrudService::class);
    $service->shouldReceive('list')->once()->andThrow(new InvalidArgumentException(
        "Aggregate 'not_a_relation' is not an Eloquent relation on Setting, so it cannot be counted or summed.",
    ));
    app()->instance(CrudService::class, $service);

    $response = $this->getJson(route('core.crud.list', ['module' => 'core', 'entity' => 'settings']));

    $response->assertStatus(Response::HTTP_BAD_REQUEST);

    // The whole point of the split: the explanation has to survive into the payload.
    expect((string) $response->json('error'))->toContain('not_a_relation');
});

it('answers 403 when the deployment declares the class unlockable by nobody', function (): void {
    errorMappingActor();

    // Not a permission problem and not a conflict: no caller, however privileged, can lift a lock
    // on a class configured this way, so the answer must not invite a retry.
    config()->set('core.locking.unlock_allowed', false);
    config()->set('core.locking.can_be_unlocked', []);

    $target = User::factory()->create();
    $target->lock();

    $response = $this->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_FORBIDDEN);

    expect($target->fresh()?->isLocked())->toBeTrue();
});

it('answers 500 and reports a broken invariant instead of calling it "not modified"', function (): void {
    errorMappingActor();
    Setting::factory()->persistedWithoutApprovalCapture()->create();

    $service = Mockery::mock(CrudService::class);
    $service->shouldReceive('list')->once()->andThrow(new LogicException('The version set scope was lost.'));
    app()->instance(CrudService::class, $service);

    $response = $this->getJson(route('core.crud.list', ['module' => 'core', 'entity' => 'settings']));

    $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
});
