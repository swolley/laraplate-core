<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Schema\FieldDefinition;
use Modules\Core\Search\SearchableContributorRegistry;
use Modules\Core\Tests\Stubs\Search\StubMediaContributor;
use Modules\Core\Tests\Stubs\Search\StubOtherSearchableModel;
use Modules\Core\Tests\Stubs\Search\StubSearchableModel;

it('returns nothing when no contributor is registered', function (): void {
    $registry = new SearchableContributorRegistry();

    expect($registry->fieldsFor(new StubSearchableModel()))->toBe([])
        ->and($registry->mappingFor(StubSearchableModel::class))->toBe([]);
});

it('merges a registered contributor into the document', function (): void {
    $registry = new SearchableContributorRegistry();
    $registry->register(new StubMediaContributor());

    expect($registry->fieldsFor(new StubSearchableModel()))
        ->toBe(['idea' => 'freshness', 'intent' => 'inform']);
});

it('merges a registered contributor into the mapping', function (): void {
    $registry = new SearchableContributorRegistry();
    $registry->register(new StubMediaContributor());

    $mapping = $registry->mappingFor(StubSearchableModel::class);

    expect($mapping)->toHaveCount(2)
        ->and($mapping[0])->toBeInstanceOf(FieldDefinition::class)
        ->and($mapping[0]->name)->toBe('idea');
});

it('does not contribute to an unrelated model class', function (): void {
    $registry = new SearchableContributorRegistry();
    $registry->register(new StubMediaContributor());

    expect($registry->fieldsFor(new StubOtherSearchableModel()))->toBe([]);
});

it('matches subclasses of a contributor target', function (): void {
    $registry = new SearchableContributorRegistry();
    $registry->register(new StubMediaContributor(Model::class));

    // StubSearchableModel is a subclass of the base Model target.
    expect($registry->fieldsFor(new StubSearchableModel()))
        ->toBe(['idea' => 'freshness', 'intent' => 'inform']);
});
