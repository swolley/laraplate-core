<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Casts\SearchMode;
use Modules\Core\Http\Requests\LoginRequest;
use Modules\Core\Http\Requests\SearchRequest;
use Modules\Core\Http\Requests\TranslationsRequest;
use Modules\Core\Search\Enums\TextMatchPreference;


it('login request defines expected validation rules', function (): void {
    $rules = (new LoginRequest())->rules();

    expect($rules)->toHaveKeys(['username', 'email', 'password', 'rememberme'])
        ->and($rules['username'])->toContain('required_without:email')
        ->and($rules['email'])->toContain('required_without:username')
        ->and($rules['password'])->toContain('required')
        ->and($rules['rememberme'])->toContain('nullable');
});

it('translations request defines expected prefix rule', function (): void {
    $rules = (new TranslationsRequest())->rules();

    expect($rules)->toHaveKey('prefix')
        ->and($rules['prefix'])->toContain('nullable')
        ->and($rules['prefix'])->toContain('string');
});

it('search request removes count and relation/sort/group rules', function (): void {
    $request = SearchRequest::create('/core/api/search/settings', 'GET', ['qs' => 'john']);
    $route = new Route('GET', '/core/api/search/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);

    $rules = $request->rules();

    expect($rules)->toHaveKey('qs')
        ->and($rules)->not->toHaveKey('count')
        ->and(collect(array_keys($rules))->contains(fn (string $k): bool => str_contains($k, 'sort.')))->toBeFalse()
        ->and(collect(array_keys($rules))->contains(fn (string $k): bool => str_contains($k, 'group_by.')))->toBeFalse()
        ->and(collect(array_keys($rules))->contains(fn (string $k): bool => str_contains($k, 'relations.')))->toBeFalse();
});

it('search request parsed returns expected DTO values', function (): void {
    $request = SearchRequest::create('/core/api/search/settings', 'GET', ['qs' => 'john']);
    $route = new Route('GET', '/core/api/search/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $validator = Mockery::mock(Illuminate\Contracts\Validation\Validator::class);
    $validator->shouldReceive('validated')->andReturn(['qs' => 'john']);
    $request->setValidator($validator);

    $parsed = $request->parsed();

    expect($parsed->mainEntity)->toBe('settings')
        ->and($parsed->primaryKey)->toBe('id')
        ->and($parsed->qs)->toBe('john')
        ->and($parsed->mode)->toBe(SearchMode::Auto)
        ->and($parsed->matching)->toBe(TextMatchPreference::Auto)
        ->and($parsed->matching_options)->toBe([]);
});

it('search request parsed casts mode to enum', function (): void {
    $request = SearchRequest::create('/core/api/search/settings', 'GET', [
        'qs' => 'john',
        'mode' => 'orchestrated',
    ]);
    $route = new Route('GET', '/core/api/search/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $validator = Mockery::mock(Illuminate\Contracts\Validation\Validator::class);
    $validator->shouldReceive('validated')->andReturn([
        'qs' => 'john',
        'mode' => 'orchestrated',
    ]);
    $request->setValidator($validator);

    $parsed = $request->parsed();

    expect($parsed->mode)->toBe(SearchMode::Orchestrated);
});

it('search request validates and parses adaptive matching controls', function (): void {
    $request = SearchRequest::create('/core/api/search/settings', 'GET', [
        'qs' => 'Mario Rossi',
        'matching' => 'tolerant',
        'matching_options' => [
            'max_edits' => 2,
            'minimum_should_match' => 80,
            'identifier_typos' => false,
        ],
    ]);
    $route = new Route('GET', '/core/api/search/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $validator = Mockery::mock(Illuminate\Contracts\Validation\Validator::class);
    $validator->shouldReceive('validated')->andReturn([
        'qs' => 'Mario Rossi',
        'matching' => 'tolerant',
        'matching_options' => [
            'max_edits' => 2,
            'minimum_should_match' => 80,
            'identifier_typos' => false,
        ],
    ]);
    $request->setValidator($validator);

    $parsed = $request->parsed();

    expect($request->rules())->toHaveKeys([
        'matching',
        'matching_options.max_edits',
        'matching_options.minimum_should_match',
        'matching_options.identifier_typos',
    ])->and($parsed->matching)->toBe(TextMatchPreference::Tolerant)
        ->and($parsed->matching_options['max_edits'])->toBe(2);
});
