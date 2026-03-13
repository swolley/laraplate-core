<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Helpers\HasValidity;
use Modules\Core\Tests\LaravelTestCase;
use Modules\Core\Tests\Stubs\ValidityStubModel;

uses(LaravelTestCase::class);

beforeEach(function (): void {
    Schema::create('validity_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamp('valid_from')->nullable();
        $table->timestamp('valid_to')->nullable();
        $table->timestamps();
    });
});

it('returns valid from and valid to column names', function (): void {
    expect(ValidityStubModel::validFromKey())->toBe('valid_from')
        ->and(ValidityStubModel::validToKey())->toBe('valid_to');
});

it('isValid returns true when date is within range', function (): void {
    $from = now()->subDay();
    $to = now()->addDay();
    $model = ValidityStubModel::create(['name' => 'x', 'valid_from' => $from, 'valid_to' => $to]);
    expect($model->isValid(now()))->toBeTrue();
});

it('isValid returns false when date is before valid_from', function (): void {
    $from = now()->addDay();
    $to = now()->addDays(2);
    $model = ValidityStubModel::create(['name' => 'y', 'valid_from' => $from, 'valid_to' => $to]);
    expect($model->isValid(now()))->toBeFalse();
});

it('isDraft returns true when valid_from is null', function (): void {
    $model = ValidityStubModel::create(['name' => 'z', 'valid_from' => null, 'valid_to' => null]);
    expect($model->isDraft())->toBeTrue();
});

it('isExpired returns true when valid_to is in the past', function (): void {
    $model = ValidityStubModel::create(['name' => 'w', 'valid_from' => now()->subDays(2), 'valid_to' => now()->subDay()]);
    expect($model->isExpired())->toBeTrue();
});

it('publish sets valid_from and valid_to in memory', function (): void {
    $model = ValidityStubModel::create(['name' => 'p', 'valid_from' => null, 'valid_to' => null]);
    $model->publish();
    expect($model->valid_from)->not->toBeNull()
        ->and($model->valid_to)->toBeNull();
    $model->save();
    $model->refresh();
    expect($model->valid_from)->not->toBeNull()
        ->and($model->valid_to)->toBeNull();
});

it('unpublish clears valid_from and valid_to in memory and persists when saved', function (): void {
    $model = ValidityStubModel::create(['name' => 'u', 'valid_from' => now(), 'valid_to' => null]);
    $model->unpublish();
    expect($model->valid_from)->toBeNull()
        ->and($model->valid_to)->toBeNull();
    $model->save();
    $model->refresh();
    expect($model->valid_from)->toBeNull()
        ->and($model->valid_to)->toBeNull();
});