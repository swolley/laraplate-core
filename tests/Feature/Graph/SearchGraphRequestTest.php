<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Core\Http\Requests\SearchGraphRequest;

beforeEach(function (): void {
    Route::get('/test/graph/search/{module}/{entity}', static function (SearchGraphRequest $request): array {
        $parsed = $request->parsed();

        return [
            'entity' => $parsed->mainEntity,
            'module' => $parsed->module,
            'qs' => $parsed->qs,
            'relations' => $parsed->graphRelations,
            'depth' => $parsed->depth,
            'relationLimit' => $parsed->relationLimit,
            'nodeDetail' => $parsed->nodeDetail,
        ];
    })->middleware('web');
});

it('parses graph search parameters from a crud search style request', function (): void {
    $response = $this->getJson('/test/graph/search/Core/users?qs=alice&relations[]=roles.permissions&depth=2&relation_limit=5&node_detail=minimal');

    $response->assertOk()
        ->assertJsonPath('entity', 'users')
        ->assertJsonPath('module', 'Core')
        ->assertJsonPath('qs', 'alice')
        ->assertJsonPath('relations.0', 'roles.permissions')
        ->assertJsonPath('depth', 2)
        ->assertJsonPath('relationLimit', 5)
        ->assertJsonPath('nodeDetail', 'minimal');
});

it('keeps qs as the search query parameter', function (): void {
    $this->getJson('/test/graph/search/Core/users?q=alice')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qs']);
});
