<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;
use Modules\Core\Overrides\CustomSoftDeletingScope;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('applies scope with is_deleted false', function (): void {
    $scope = new CustomSoftDeletingScope();
    $model = new Setting();
    $builder = Setting::query();

    $scope->apply($builder, $model);

    $sql = $builder->toSql();
    expect($sql)->toContain('is_deleted');
    expect($builder->getBindings())->toContain(false);
});

it('adds withoutTrashed macro when extend is invoked', function (): void {
    $scope = new CustomSoftDeletingScope();
    $builder = Setting::query();
    $ref = new \ReflectionClass($scope);
    $method = $ref->getMethod('addWithoutTrashed');
    $method->invoke($scope, $builder);

    expect($builder->getMacro('withoutTrashed'))->not->toBeNull();
});

it('adds onlyTrashed macro when extend is invoked', function (): void {
    $scope = new CustomSoftDeletingScope();
    $builder = Setting::query();
    $ref = new \ReflectionClass($scope);
    $method = $ref->getMethod('addOnlyTrashed');
    $method->invoke($scope, $builder);

    expect($builder->getMacro('onlyTrashed'))->not->toBeNull();
});
