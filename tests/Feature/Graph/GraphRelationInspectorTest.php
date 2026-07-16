<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Graph\GraphRelationInspector;
use Modules\Core\Tests\Stubs\Graphs\GraphInspectorChild;
use Modules\Core\Tests\Stubs\Graphs\GraphInspectorParent;

it('inspects normal eloquent relations', function (): void {
    $relation = (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'children');

    expect($relation->name)->toBe('children');
    expect($relation->relatedClass)->toBe(GraphInspectorChild::class);
    expect($relation->isMultiple)->toBeTrue();
});

it('rejects missing relations with validation errors', function (): void {
    expect(fn () => (new GraphRelationInspector())->inspect(new GraphInspectorParent(), 'missing'))
        ->toThrow(ValidationException::class);
});
