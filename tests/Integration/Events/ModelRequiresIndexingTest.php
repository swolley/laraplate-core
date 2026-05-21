<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Events\ModelRequiresIndexing;

it('allPreProcessingCompleted returns true when no pre-processing required', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);

    expect($event->allPreProcessingCompleted())->toBeTrue();
});

it('allPreProcessingCompleted returns false when pre-processing is pending', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);
    $event->addRequiredPreProcessing('embeddings');

    expect($event->allPreProcessingCompleted())->toBeFalse();
});

it('allPreProcessingCompleted returns true when all pre-processing completed', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);
    $event->addRequiredPreProcessing('embeddings');
    $event->addRequiredPreProcessing('translations');
    $event->markPreProcessingCompleted('embeddings');
    $event->markPreProcessingCompleted('translations');

    expect($event->allPreProcessingCompleted())->toBeTrue();
});

it('markAsHandled sets handled flag', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);

    expect($event->isHandled())->toBeFalse();

    $event->markAsHandled();

    expect($event->isHandled())->toBeTrue();
});

it('addRequiredPreProcessing does not add duplicates', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);
    $event->addRequiredPreProcessing('embeddings');
    $event->addRequiredPreProcessing('embeddings');

    expect($event->required_pre_processing)->toHaveCount(1);
});

it('markPreProcessingCompleted does not add duplicates', function (): void {
    $model = Mockery::mock(Model::class);
    $event = new ModelRequiresIndexing($model);
    $event->markPreProcessingCompleted('embeddings');
    $event->markPreProcessingCompleted('embeddings');

    expect($event->completed_pre_processing)->toHaveCount(1);
});
