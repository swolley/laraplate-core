<?php

declare(strict_types=1);

use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DomainActionRegistry;

beforeEach(function (): void {
    $this->actor = User::factory()->create();
    $this->actor->assignRole(Role::findOrCreate('superadmin', 'web'));
    $this->actingAs($this->actor);
});

it('routes a registered domain action to its handler', function (): void {
    $target = User::factory()->create();

    app(DomainActionRegistry::class)->register(
        User::class,
        'archive',
        fn (User $record, array $payload): array => ['id' => $record->id, 'payload' => $payload],
    );

    $response = $this->postJson(
        route('core.crud.domain-action', ['action' => 'archive', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id, 'reason' => 'obsolete'],
    );

    $response->assertOk()
        ->assertJsonPath('data.payload.reason', 'obsolete')
        ->assertJsonPath('data.id', $target->id);
});

it('returns 404 for an action nobody registered', function (): void {
    $target = User::factory()->create();

    $response = $this->postJson(
        route('core.crud.domain-action', ['action' => 'nope', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertNotFound();
});

it('returns 404 for a record that does not exist', function (): void {
    app(DomainActionRegistry::class)->register(User::class, 'archive_missing', fn (): null => null);

    $response = $this->postJson(
        route('core.crud.domain-action', ['action' => 'archive_missing', 'module' => 'core', 'entity' => 'users']),
        ['id' => 999999],
    );

    $response->assertNotFound();
});

it('passes a streamed response through untouched', function (): void {
    $target = User::factory()->create();

    app(DomainActionRegistry::class)->register(
        User::class,
        'export_something',
        fn (): Symfony\Component\HttpFoundation\Response => response('col_a,col_b', 200, ['Content-Type' => 'text/csv']),
    );

    $response = $this->post(
        route('core.crud.domain-action', ['action' => 'export_something', 'module' => 'core', 'entity' => 'users']),
        ['id' => $target->id],
    );

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->getContent())->toBe('col_a,col_b');
});
