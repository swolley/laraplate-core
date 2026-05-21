<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Tests\Fixtures\FakeTranslatableModel;


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
});

it('creates a default translation when missing, then may duplicate others', function (): void {
    config(['app.locale' => 'en', 'app.available_locales' => ['en', 'it']]);

    $model = FakeTranslatableModel::factory()->create();

    expect($model->translations()->where('locale', 'en')->exists())->toBeTrue();
    expect($model->getTranslation('en')?->title)->toBe('Overridden Title');
});
