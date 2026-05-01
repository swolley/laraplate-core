<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\User;
use Modules\Core\Tests\Stubs\UniqueFactoryHarness;
use Modules\Core\Tests\Stubs\UniqueFactoryQueryThrows;


it('returns first candidate when model and column are omitted', function (): void {
    $harness = new UniqueFactoryHarness;
    $i = 0;
    $value = $harness->exposeUniqueValue(static function () use (&$i): string {
        return 'fixed-' . ($i++);
    });

    expect($value)->toBe('fixed-0');
});

it('uniqueSlug delegates to uniqueValue with slugged name', function (): void {
    $harness = new UniqueFactoryHarness;
    $slug = $harness->exposeUniqueSlug('Hello World');

    expect($slug)->toBe('hello-world');
});

it('uniqueEmail returns a string', function (): void {
    $harness = new UniqueFactoryHarness;
    $email = $harness->exposeUniqueEmail(null, static fn (): string => 'plain-' . uniqid('', true) . '@example.com');

    expect($email)->toBeString()->toContain('@');
});

it('uniquePhoneNumber and uniqueUrl return strings', function (): void {
    $harness = new UniqueFactoryHarness;

    expect($harness->exposeUniquePhoneNumber())->toBeString()
        ->and($harness->exposeUniqueUrl())->toBeString();
});

it('respects database uniqueness when model and column are provided', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $harness = new UniqueFactoryHarness;
    $email = $harness->exposeUniqueEmail(User::class, static fn (): string => 'taken@example.com', 'email', 20);

    expect($email)->not->toBe('taken@example.com');
});

it('throws when all candidates collide in database within max attempts', function (): void {
    User::factory()->create(['email' => 'collision@example.com']);

    $harness = new UniqueFactoryHarness;

    expect(fn () => $harness->exposeUniqueValue(
        static fn (): string => 'collision@example.com',
        User::class,
        'email',
        1,
    ))->toThrow(Exception::class, 'Failed to generate a unique value after 1 attempts');
});

it('skips database check when the model connection resolver is unset', function (): void {
    $resolver = Model::getConnectionResolver();
    Model::unsetConnectionResolver();

    try {
        $harness = new UniqueFactoryHarness;
        $value = $harness->exposeUniqueValue(
            static fn (): string => 'resolver-off@example.com',
            User::class,
            'email',
            5,
        );

        expect($value)->toBe('resolver-off@example.com');
    } finally {
        Model::setConnectionResolver($resolver);
    }
});

it('returns first generated value when uniqueness query throws', function (): void {
    $harness = new UniqueFactoryHarness;

    $value = $harness->exposeUniqueValue(
        static fn (): string => 'after-query-throw@example.com',
        UniqueFactoryQueryThrows::class,
        'email',
        5,
    );

    expect($value)->toBe('after-query-throw@example.com');
});

it('generateFallbackValue returns synthetic string when faker callable throws', function (): void {
    $harness = new UniqueFactoryHarness;
    $method = new ReflectionMethod(UniqueFactoryHarness::class, 'generateFallbackValue');
    $method->setAccessible(true);

    $result = $method->invoke($harness, static fn (): string => throw new Exception('exhausted'));

    expect($result)->toStartWith('generated_');
});
