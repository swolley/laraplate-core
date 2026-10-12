<?php

declare(strict_types=1);

use Modules\Core\Authorization\PartOwnerResolver;
use Modules\Core\Tests\Stubs\Crud\RelationCyclicPartStub;
use Modules\Core\Tests\Stubs\Crud\RelationMisdeclaredPartStub;
use Modules\Core\Tests\Stubs\Crud\RelationOwnerStub;
use Modules\Core\Tests\Stubs\Crud\RelationPartStub;
use Modules\Core\Tests\Stubs\Crud\RelationSubPartStub;

/*
 * The owner of a part is the first model up its chain of parent relations that is not a part. A chain that does
 * not resolve answers null, so the hydration check keeps the part's own permission instead of allowing.
 */

it('resolves the owner of a part and of a part of a part', function (): void {
    expect(PartOwnerResolver::ownerClassOf(new RelationPartStub))->toBe(RelationOwnerStub::class)
        ->and(PartOwnerResolver::ownerClassOf(new RelationSubPartStub))->toBe(RelationOwnerStub::class);
});

it('resolves no owner for a model that is not a part', function (): void {
    expect(PartOwnerResolver::ownerClassOf(new RelationOwnerStub))->toBeNull();
});

it('resolves no owner when the chain does not reach one', function (): void {
    expect(PartOwnerResolver::ownerClassOf(new RelationMisdeclaredPartStub))->toBeNull()
        ->and(PartOwnerResolver::ownerClassOf(new RelationCyclicPartStub))->toBeNull();
});
