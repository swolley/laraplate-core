<?php

declare(strict_types=1);

use Modules\Core\Authorization\RetrievedSelectGuard;

it('suppresses only within the callback scope', function (): void {
    expect(RetrievedSelectGuard::isSuppressed('Acme\\Foo'))->toBeFalse();

    $inside = RetrievedSelectGuard::without('Acme\\Foo', function (): bool {
        return RetrievedSelectGuard::isSuppressed('Acme\\Foo');
    });

    expect($inside)->toBeTrue()
        ->and(RetrievedSelectGuard::isSuppressed('Acme\\Foo'))->toBeFalse();
});

it('restores the suppression state even when the callback throws', function (): void {
    expect(fn () => RetrievedSelectGuard::without('Acme\\Bar', function (): void {
        throw new RuntimeException('boom');
    }))->toThrow(RuntimeException::class);

    expect(RetrievedSelectGuard::isSuppressed('Acme\\Bar'))->toBeFalse();
});

it('keeps suppression until the outermost nested scope exits', function (): void {
    RetrievedSelectGuard::without('Acme\\Baz', function (): void {
        RetrievedSelectGuard::without('Acme\\Baz', function (): void {
            expect(RetrievedSelectGuard::isSuppressed('Acme\\Baz'))->toBeTrue();
        });

        // Still suppressed: the outer scope has not exited yet.
        expect(RetrievedSelectGuard::isSuppressed('Acme\\Baz'))->toBeTrue();
    });

    expect(RetrievedSelectGuard::isSuppressed('Acme\\Baz'))->toBeFalse();
});

it('scopes suppression to the exact class, not siblings', function (): void {
    RetrievedSelectGuard::without('Acme\\Main', function (): void {
        expect(RetrievedSelectGuard::isSuppressed('Acme\\Main'))->toBeTrue()
            ->and(RetrievedSelectGuard::isSuppressed('Acme\\Relation'))->toBeFalse();
    });
});
