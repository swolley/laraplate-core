<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Auth\Access\AuthorizationException;
use Modules\Core\Tests\Stubs\SoftDeletesStubModel;


beforeEach(function (): void {
    Schema::create('soft_deletes_stub', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->timestamp('deleted_at')->nullable();
        $table->boolean('is_deleted')->default(false);
        $table->timestamps();
    });
});

it('exposes is_deleted column helpers', function (): void {
    $model = new SoftDeletesStubModel;

    expect($model->getIsDeletedColumn())->toBe('is_deleted')
        ->and($model->getQualifiedIsDeletedColumn())->toContain('is_deleted');
});

it('guards and hides soft delete columns on initialize', function (): void {
    $model = new SoftDeletesStubModel;
    $model->initializeSoftDeletes();

    expect($model->getHidden())->toContain('deleted_at')
        ->and($model->getHidden())->toContain('is_deleted')
        ->and($model->getGuarded())->toContain('is_deleted');
});

it('uses model property to disable soft deletes persistence', function (): void {
    $model = new class extends SoftDeletesStubModel
    {
        protected bool $softDeletesEnabled = false;
    };

    expect($model->softDeletesEnabledBySettings())->toBeFalse();
});

it('detects trashed state from original is_deleted value', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['is_deleted' => true], true);
    $model->setRawAttributes([]);

    expect($model->trashed())->toBeTrue();
});

it('detects trashed state from original deleted_at value', function (): void {
    $model = new SoftDeletesStubModel;
    $model->setRawAttributes(['deleted_at' => now()], true);
    $model->setRawAttributes([]);

    expect($model->trashed())->toBeTrue();
});

it('returns false when no soft delete state is present', function (): void {
    expect((new SoftDeletesStubModel)->trashed())->toBeFalse();
});

it('restores soft deleted models when soft deletes persistence is enabled', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'restorable']);
    $model->delete();

    expect($model->restore())->toBeTrue()
        ->and($model->fresh()->trashed())->toBeFalse();
});

it('throws when updating a trashed model', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'a']);
    $model->delete();

    try {
        $model->update(['name' => 'b']);
        expect(false)->toBeTrue('Expected AuthorizationException');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(AuthorizationException::class);
        expect($e->getMessage())->toContain('Cannot update');
    }
});
