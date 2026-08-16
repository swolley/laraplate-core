<?php

declare(strict_types=1);

use Modules\Core\Events\AiTextGenerationRequested;

test('a fresh request is unfulfilled and carries its prompt, purpose and context', function (): void {
    $event = new AiTextGenerationRequested('rewrite this', 'sao.ownership_suggestion', ['ticket' => 7]);

    expect($event->isFulfilled())->toBeFalse()
        ->and($event->response)->toBeNull()
        ->and($event->prompt)->toBe('rewrite this')
        ->and($event->purpose)->toBe('sao.ownership_suggestion')
        ->and($event->context)->toBe(['ticket' => 7]);
});

test('fulfilling records the response and marks it fulfilled', function (): void {
    $event = new AiTextGenerationRequested('rewrite this', 'sao.ownership_suggestion');
    $event->fulfill('a natural sentence');

    expect($event->response)->toBe('a natural sentence')
        ->and($event->isFulfilled())->toBeTrue();
});

test('an empty response is not considered fulfilled', function (): void {
    $event = new AiTextGenerationRequested('rewrite this', 'sao.ownership_suggestion');
    $event->fulfill('');

    expect($event->isFulfilled())->toBeFalse();
});
