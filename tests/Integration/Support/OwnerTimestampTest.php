<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\OwnerTimestamp;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;

/**
 * The owner's `updated_at` is moved by a direct UPDATE of that column, without model events, once per owner
 * and per transaction (D37, D51 of the revision-centric history review).
 */
beforeEach(function (): void {
    Schema::create('fake_translatable_models', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });

    Schema::create('fake_translatable_model_translations', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('fake_translatable_model_id')->constrained('fake_translatable_models')->cascadeOnDelete();
        $table->string('locale');
        $table->string('title')->nullable();
        $table->string('slug')->nullable();
        $table->json('components')->nullable();
        $table->timestamps();
    });

    CarbonImmutable::setTestNow('2026-10-19 10:00:00');
    $model = new FakeTranslatableModel;
    $id = $model->newQueryWithoutScopes()->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    $this->owner = $model->newQueryWithoutScopes()->findOrFail($id);
    $this->updates = 0;
    $this->owner->getConnection()->listen(function ($query): void {
        if (str_starts_with(mb_strtolower($query->sql), 'update') && str_contains($query->sql, 'fake_translatable_models')) {
            $this->updates++;
        }
    });
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Schema::dropIfExists('fake_translatable_model_translations');
    Schema::dropIfExists('fake_translatable_models');
});

function owner_updated_at(FakeTranslatableModel $owner): string
{
    return (string) $owner->newQueryWithoutScopes()->whereKey($owner->getKey())->toBase()->value('updated_at');
}

it('moves the updated_at of the owner without firing its events', function (): void {
    $fired = 0;
    FakeTranslatableModel::updated(function () use (&$fired): void {
        $fired++;
    });
    CarbonImmutable::setTestNow('2026-10-19 11:00:00');

    OwnerTimestamp::touch($this->owner, $this->owner->getKey());

    expect(owner_updated_at($this->owner))->toStartWith('2026-10-19 11:00:00')
        ->and($fired)->toBe(0);
});

it('touches an owner once per transaction', function (): void {
    $this->owner->getConnection()->transaction(function (): void {
        foreach (range(1, 10) as $ignored) {
            OwnerTimestamp::touch($this->owner, $this->owner->getKey());
        }
    });

    expect($this->updates)->toBe(1);
});

it('touches again after a rolled-back savepoint that had touched the owner', function (): void {
    $connection = $this->owner->getConnection();

    $connection->transaction(function () use ($connection): void {
        try {
            $connection->transaction(function (): void {
                OwnerTimestamp::touch($this->owner, $this->owner->getKey());

                throw new RuntimeException('undo the savepoint');
            });
        } catch (RuntimeException) {
        }

        // A new savepoint at the same level as the one rolled back.
        $connection->transaction(function (): void {
            CarbonImmutable::setTestNow('2026-10-19 12:00:00');
            OwnerTimestamp::touch($this->owner, $this->owner->getKey());
        });
    });

    expect(owner_updated_at($this->owner))->toStartWith('2026-10-19 12:00:00');
});

it('touches again after a rolled-back transaction that had touched the owner', function (): void {
    try {
        $this->owner->getConnection()->transaction(function (): void {
            OwnerTimestamp::touch($this->owner, $this->owner->getKey());

            throw new RuntimeException('undo');
        });
    } catch (RuntimeException) {
    }

    CarbonImmutable::setTestNow('2026-10-19 12:00:00');
    OwnerTimestamp::touch($this->owner, $this->owner->getKey());

    expect(owner_updated_at($this->owner))->toStartWith('2026-10-19 12:00:00');
});
