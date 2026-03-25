<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\ActivationStubModel;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Schema::create('activation_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });
});

it('returns activation column name', function (): void {
    expect(ActivationStubModel::activationColumn())->toBe('is_active');
});

it('reports active when column is true', function (): void {
    $model = ActivationStubModel::create(['name' => 'x', 'is_active' => true]);
    expect($model->isActive())->toBeTrue();
});

it('reports inactive when column is false', function (): void {
    $model = ActivationStubModel::create(['name' => 'y', 'is_active' => false]);
    expect($model->isActive())->toBeFalse();
});

it('activate sets is_active to true and saves', function (): void {
    $model = ActivationStubModel::create(['name' => 'z', 'is_active' => false]);
    $model->activate();
    $model->refresh();
    expect($model->is_active)->toBeTrue();
});

it('deactivate sets is_active to false and saves', function (): void {
    $model = ActivationStubModel::create(['name' => 'w', 'is_active' => true]);
    $model->deactivate();
    $model->refresh();
    expect($model->is_active)->toBeFalse();
});

it('active scope filters by is_active true', function (): void {
    ActivationStubModel::create(['name' => 'a', 'is_active' => true]);
    ActivationStubModel::create(['name' => 'b', 'is_active' => false]);
    $active = ActivationStubModel::query()->active()->get();
    expect($active)->toHaveCount(1)->and($active->first()->name)->toBe('a');
});

it('inactive scope filters by is_active false', function (): void {
    ActivationStubModel::create(['name' => 'a', 'is_active' => true]);
    ActivationStubModel::create(['name' => 'b', 'is_active' => false]);
    $inactive = ActivationStubModel::query()->inactive()->get();
    expect($inactive)->toHaveCount(1)->and($inactive->first()->name)->toBe('b');
});

it('initialization adds is_active to fillable and hidden when missing', function (): void {
    $model = new ActivationStubModel();
    expect($model->getFillable())->toContain('is_active')
        ->and($model->getHidden())->toContain('is_active');
});
