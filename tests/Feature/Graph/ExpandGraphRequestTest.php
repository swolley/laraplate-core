<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Requests\ExpandGraphRequest;

beforeEach(function (): void {
    Route::get('/test/graph/{module}/{entity}/{id}', static function (ExpandGraphRequest $request): array {
        $parsed = $request->parsed();

        return [
            'entity' => $parsed->mainEntity,
            'module' => $parsed->module,
            'recordKey' => $parsed->recordKey,
            'relations' => $parsed->graphRelations,
            'depth' => $parsed->depth,
            'limit' => $parsed->limit,
            'relationLimit' => $parsed->relationLimit,
            'nodeDetail' => $parsed->nodeDetail,
        ];
    })->middleware('web');
});

it('parses graph expand parameters from a crud detail style request', function (): void {
    $response = $this->getJson('/test/graph/Core/users/123?relations[]=roles.permissions&depth=2&limit=30&relation_limit=5&node_detail=minimal');

    $response->assertOk()
        ->assertJsonPath('entity', 'users')
        ->assertJsonPath('module', 'Core')
        ->assertJsonPath('recordKey', '123')
        ->assertJsonPath('relations.0', 'roles.permissions')
        ->assertJsonPath('depth', 2)
        ->assertJsonPath('limit', 30)
        ->assertJsonPath('relationLimit', 5)
        ->assertJsonPath('nodeDetail', 'minimal');
});

it('rejects relation paths deeper than depth', function (): void {
    $this->getJson('/test/graph/Core/users/123?relations[]=roles.permissions&depth=1')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['relations']);
});
