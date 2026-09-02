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

it('answers 409 with the reason when unlocking a record locked by someone else', function (): void {
    $actor = errorMappingActor();
    $owner = User::factory()->create();

    $target = User::factory()->create();
    $target->lockBy($owner);

    expect($target->fresh()?->locked_user_id)->toBe($owner->id)
        ->and(Auth::id())->not->toBe($owner->id);

    $response = $this->patchJson(
        route('core.crud.unlock', ['module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertStatus(Response::HTTP_CONFLICT);

    expect((string) $response->json('error'))->toContain('locked by another user')
        ->and($target->fresh()?->isLocked())->toBeTrue();
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
