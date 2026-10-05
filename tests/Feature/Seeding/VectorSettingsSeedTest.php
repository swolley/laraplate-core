<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Models\Setting;

uses(RefreshDatabase::class);

it('seeds the four vector settings as command-managed', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    $settings = Setting::query()->where('name', 'like', 'search.vector.%')->get()->keyBy('name');

    expect($settings['search.vector.dimensions']->value)->toBe(384)
        ->and($settings['search.vector.similarity']->value)->toBe('cosine')
        ->and($settings['search.vector.model']->value)->toBe('sentence_transformers:intfloat/multilingual-e5-small')
        ->and($settings['search.vector.suspended_reason']->value)->toBeNull()
        ->and($settings['search.vector.suspended_reason']->type->value)->toBe('string');

    foreach (['dimensions', 'similarity', 'model', 'suspended_reason'] as $suffix) {
        expect($settings["search.vector.{$suffix}"]->managed)->toBeTrue()
            ->and($settings["search.vector.{$suffix}"]->group_name)->toBe('search');
    }

    expect($settings['search.vector.enabled']->managed)->toBeFalse();
});

it('keeps a value a command wrote when the seeder runs again', function (): void {
    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    Setting::writeManaged('search.vector.dimensions', 1024);
    Setting::writeManaged('search.vector.model', 'openai:text-embedding-3-small');

    $this->artisan('db:seed', ['--class' => CoreDatabaseSeeder::class])->assertSuccessful();

    expect(Setting::query()->where('name', 'search.vector.dimensions')->value('value'))->toBe(1024)
        ->and(Setting::query()->where('name', 'search.vector.model')->value('value'))->toBe('openai:text-embedding-3-small')
        ->and(Setting::query()->where('name', 'search.vector.dimensions')->value('managed'))->toBeTruthy();
});
