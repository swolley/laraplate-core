<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\UnauthorizedException;
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

it('throws when updating a trashed model', function (): void {
    $model = SoftDeletesStubModel::create(['name' => 'a']);
    $model->delete();

    try {
        $model->update(['name' => 'b']);
        expect(false)->toBeTrue('Expected UnauthorizedException');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(UnauthorizedException::class);
        expect($e->getMessage())->toContain('Cannot update');
    }
});
