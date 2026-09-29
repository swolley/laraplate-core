<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\ResourceSchemaContributorRegistry;
use Modules\Core\Tests\Stubs\Filament\StubSchemaContributor;
use Modules\Core\Tests\Stubs\Search\StubOtherSearchableModel;
use Modules\Core\Tests\Stubs\Search\StubSearchableModel;

it('returns nothing when no contributor is registered', function (): void {
    $registry = new ResourceSchemaContributorRegistry();

    expect($registry->infolistSectionsFor(new StubSearchableModel()))->toBe([])
        ->and($registry->recordActionsFor(new StubSearchableModel()))->toBe([]);
});

it('returns a registered contributor sections and actions', function (): void {
    $registry = new ResourceSchemaContributorRegistry();
    $registry->register(new StubSchemaContributor());

    expect($registry->infolistSectionsFor(new StubSearchableModel()))->toBe(['analysis-section'])
        ->and($registry->recordActionsFor(new StubSearchableModel()))->toBe(['re-analyze-action']);
});

it('does not contribute to an unrelated model class', function (): void {
    $registry = new ResourceSchemaContributorRegistry();
    $registry->register(new StubSchemaContributor());

    expect($registry->infolistSectionsFor(new StubOtherSearchableModel()))->toBe([])
        ->and($registry->recordActionsFor(new StubOtherSearchableModel()))->toBe([]);
});

it('matches subclasses of a contributor target', function (): void {
    $registry = new ResourceSchemaContributorRegistry();
    $registry->register(new StubSchemaContributor(Model::class));

    // StubSearchableModel is a subclass of the base Model target.
    expect($registry->infolistSectionsFor(new StubSearchableModel()))->toBe(['analysis-section']);
});
