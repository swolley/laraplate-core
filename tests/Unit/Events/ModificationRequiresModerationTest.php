<?php

declare(strict_types=1);

use Modules\Core\Events\ModificationRequiresModeration;
use Modules\Core\Models\Modification;

it('tracks pre-processing orchestration like model indexing', function (): void {
    $modification = new Modification();
    $event = new ModificationRequiresModeration($modification);

    $event->addRequiredPreProcessing('ai_approval');
    $event->markPreProcessingCompleted('ai_approval');

    expect($event->allPreProcessingCompleted())->toBeTrue()
        ->and($event->isHandled())->toBeFalse();

    $event->markAsHandled();

    expect($event->isHandled())->toBeTrue();
});
