<?php

declare(strict_types=1);

use Illuminate\Database\MultipleRecordsFoundException;
use Illuminate\Database\RecordsNotFoundException;
use Modules\Core\Models\Role;
use Modules\Core\Models\Setting;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\CrudService;
use Symfony\Component\HttpFoundation\Response;

/**
 * `detail` and `history` resolve a single record with `sole()`, which fails in two
 * different ways. The two must not collapse onto one status: "nothing matched" tells
 * the client to stop, "several matched" tells it to narrow the criteria.
 */
function singleRecordRouteParams(): array
{
    return ['module' => 'core', 'entity' => 'settings'];
}

function actAsSuperadmin(): User
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('superadmin', 'web'));

    test()->actingAs($actor);

    return $actor;
}

it('answers 400 when the criteria match more than one record', function (): void {
    actAsSuperadmin();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();

    $service = Mockery::mock(CrudService::class);
    $service->shouldReceive('detail')->once()->andThrow(new MultipleRecordsFoundException(2));
    app()->instance(CrudService::class, $service);

    $response = $this->getJson(
        route('core.crud.detail', singleRecordRouteParams()) . '?' . http_build_query(['id' => $setting->id]),
    );

    $response->assertStatus(Response::HTTP_BAD_REQUEST);

    // The count has to survive into the payload: a bare 400 does not tell the caller
    // that the fix is a narrower filter.
    expect((string) $response->json('error'))->toContain('2');
});

it('still answers 404 when the criteria match no record', function (): void {
    actAsSuperadmin();
    $setting = Setting::factory()->persistedWithoutApprovalCapture()->create();

    $service = Mockery::mock(CrudService::class);
    $service->shouldReceive('detail')->once()->andThrow(new RecordsNotFoundException());
    app()->instance(CrudService::class, $service);

    $response = $this->getJson(
        route('core.crud.detail', singleRecordRouteParams()) . '?' . http_build_query(['id' => $setting->id]),
    );

    $response->assertStatus(Response::HTTP_NOT_FOUND);
});
