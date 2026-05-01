<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Modules\Core\Http\Requests\CrudRequest;
use Modules\Core\Http\Requests\SelectRequest;


it('crud request exposes base rules, primary key and parsed payload', function (): void {
    $request = new class() extends CrudRequest
    {
        public function validated($key = null, $default = null): array
        {
            return [];
        }
    };

    $route = new Route('GET', '/core/api/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);

    $rules = $request->rules();
    $parsed = $request->parsed();

    expect($rules)->toHaveKey('connection')
        ->and($request->getPrimaryKey())->toBe('id')
        ->and($parsed->mainEntity)->toBe('settings')
        ->and($parsed->primaryKey)->toBe('id');
});

it('crud request prepareForValidation resolves model and updates primary key', function (): void {
    $request = new class() extends CrudRequest
    {
        public function runPrepare(): void
        {
            $this->prepareForValidation();
        }
    };

    $route = new Route('GET', '/core/api/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $request->merge(['connection' => null]);

    $request->runPrepare();

    expect($request->getPrimaryKey())->toBe((new Modules\Core\Models\Setting())->getKeyName());
});

it('select request decodes columns and relations strings during prepareForValidation', function (): void {
    $request = new class() extends SelectRequest
    {
        public function validated($key = null, $default = null): array
        {
            return [];
        }

        public function runPrepare(): void
        {
            $this->prepareForValidation();
        }
    };

    $route = new Route('GET', '/core/api/{entity}', fn (): null => null);
    $route->bind($request);
    $route->setParameter('entity', 'settings');
    $request->setRouteResolver(fn (): Route => $route);
    $request->merge([
        'columns' => 'id,name',
        'relations' => '[{"name":"translations"}]',
    ]);

    $request->runPrepare();
    $rules = $request->rules();
    $parsed = $request->parsed();

    expect($request->input('columns'))->toBeArray()
        ->and($request->input('relations'))->toBeArray()
        ->and($rules)->toHaveKey('columns.*')
        ->and($rules)->toHaveKey('relations')
        ->and($parsed->mainEntity)->toBe('settings');
});

it('select request decode handles csv and array relation payloads', function (): void {
    $method = new ReflectionMethod(SelectRequest::class, 'decode');
    $method->setAccessible(true);

    $from_csv = $method->invoke(null, 'name,translations');
    $from_json = $method->invoke(null, '[{"name":"translations","foo":"bar"}]');

    expect($from_csv[0]['name'])->toBe('name')
        ->and($from_csv[1]['name'])->toBe('translations')
        ->and($from_json[0]['name'])->toBe('translations')
        ->and($from_json[0]['foo'])->toBe('bar');
});
