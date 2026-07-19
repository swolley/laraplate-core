<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Modules\Core\Graph\Data\GraphExpandToolInput;
use Modules\Core\Graph\Data\GraphSearchToolInput;
use Modules\Core\Graph\Data\GraphStatsToolInput;
use Modules\Core\Graph\GraphService;
use Modules\Core\Graph\GraphToolGateway;
use Modules\Core\Models\User;
use Modules\Core\Services\Crud\DTOs\CrudResult;

it('exposes bounded read-only input DTOs without identity or query internals', function (): void {
    $search = new GraphSearchToolInput('Core', 'users', 'alice', ['roles'], 1, 5, 5);
    $expand = new GraphExpandToolInput('Core', 'users', '10', ['roles'], 1, 10, 5);
    $stats = new GraphStatsToolInput('Core', 'users', '10', ['roles'], 1, 10, 5);

    expect($search->limit)->toBe(5)
        ->and($expand->limit)->toBe(10)
        ->and($stats->relationLimit)->toBe(5);

    foreach ([$search, $expand, $stats] as $input) {
        expect(get_object_vars($input))->not->toHaveKeys([
            'user_id',
            'tenant_id',
            'permissions',
            'connection',
            'class',
            'sql',
            'query_json',
        ]);
    }

    expect(fn () => new GraphExpandToolInput('Core', 'users', '10', ['roles'], 3, 100, 100))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => new GraphExpandToolInput('Core', 'users', '10'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => new GraphStatsToolInput('Core', 'users', '10'))
        ->toThrow(InvalidArgumentException::class);
});

it('uses the authenticated request and removes sensitive attributes from graph results', function (): void {
    config()->set('graph.assistant_safe_fields', [
        'default' => ['title', 'name', 'label', 'status', 'type', 'code'],
        'core.users' => ['name'],
    ]);

    $user = new User(['name' => 'Current User']);
    $user->setAttribute('id', 7);
    $request = Request::create('/app/ai', 'POST');
    $request->setUserResolver(static fn (): User => $user);

    $executor = static function (string $operation, object $data) use ($user): CrudResult {
        expect($operation)->toBe('search')
            ->and($data->request->user())->toBe($user)
            ->and($data->limit)->toBe(5)
            ->and($data->nodeDetail)->toBe('summary');

        return new CrudResult([
            'center' => null,
            'nodes' => [[
                'id' => 'core:users:8',
                'module' => 'core',
                'entity' => 'users',
                'key' => 8,
                'label' => 'Visible User',
                'attributes' => [
                    'name' => 'Visible User',
                    'email' => 'private@example.test',
                    'password' => 'secret',
                    'path' => '/srv/private',
                ],
            ]],
            'edges' => [],
            'graphMeta' => ['truncated' => false, 'filteredByAcl' => true],
            'searchMeta' => ['resultCount' => 1],
        ]);
    };

    $gateway = new GraphToolGateway(app(GraphService::class), $request, $executor);
    $result = $gateway->search(new GraphSearchToolInput('Core', 'users', 'alice', [], 1, 5, 5));

    expect($result['available'])->toBeTrue()
        ->and($result['nodes'][0]['attributes'])->toBe(['name' => 'Visible User'])
        ->and($result['nodes'][0])->not->toHaveKey('key')
        ->and($result)->not->toHaveKey('filteredByAcl');
});

it('maps tool failures to the same unavailable result', function (Closure $failureFactory): void {
    $request = Request::create('/app/ai', 'POST');
    $request->setUserResolver(static fn (): User => new User);
    $failure = $failureFactory();
    $executor = static fn (): never => throw $failure;
    $gateway = new GraphToolGateway(app(GraphService::class), $request, $executor);
    $input = new GraphExpandToolInput('Core', 'users', '99', ['roles'], 1, 10, 5);

    expect($gateway->expand($input))->toBe([
        'available' => false,
        'nodes' => [],
        'edges' => [],
        'truncated' => false,
    ]);
})->with([
    'unauthorized' => [static fn (): Throwable => new AuthorizationException],
    'missing' => [static fn (): Throwable => new ModelNotFoundException],
    'provider rejection' => [static fn (): Throwable => Illuminate\Validation\ValidationException::withMessages(['relations' => 'Sensitive provider rule'])],
    'internal failure' => [static fn (): Throwable => new RuntimeException('Sensitive internal failure')],
]);
