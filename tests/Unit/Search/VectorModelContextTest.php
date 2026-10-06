<?php

declare(strict_types=1);

use Modules\Core\Search\Support\VectorModelContext;

it('returns the configured model outside using()', function (): void {
    config()->set('core.search.vector.model', 'a:one');

    expect(VectorModelContext::get())->toBe('a:one');
});

it('returns null when nothing is configured', function (): void {
    config()->set('core.search.vector.model', '');
    expect(VectorModelContext::get())->toBeNull();

    config()->set('core.search.vector.model', null);
    expect(VectorModelContext::get())->toBeNull();
});

it('returns the override inside using() and the callback result', function (): void {
    config()->set('core.search.vector.model', 'a:one');

    $result = VectorModelContext::using('b:two', static fn (): ?string => VectorModelContext::get());

    expect($result)->toBe('b:two')
        ->and(VectorModelContext::get())->toBe('a:one');
});

it('supports nested overrides', function (): void {
    config()->set('core.search.vector.model', 'a:one');

    $seen = VectorModelContext::using('b:two', static function (): array {
        $outer = VectorModelContext::get();
        $inner = VectorModelContext::using('c:three', static fn (): ?string => VectorModelContext::get());

        return [$outer, $inner, VectorModelContext::get()];
    });

    expect($seen)->toBe(['b:two', 'c:three', 'b:two']);
});

it('restores the previous override when the callback throws', function (): void {
    config()->set('core.search.vector.model', 'a:one');

    try {
        VectorModelContext::using('b:two', static function (): never {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    expect(VectorModelContext::get())->toBe('a:one');
});
