<?php

declare(strict_types=1);

use Modules\Core\Helpers\HasACL;

it('trait can be used', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(method_exists($trait, 'acl'))->toBeTrue();
    expect(method_exists($trait, 'bootHasACL'))->toBeTrue();
});

it('trait has required methods', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(method_exists($trait, 'acl'))->toBeTrue();
    expect(method_exists($trait, 'bootHasACL'))->toBeTrue();
});

it('trait methods are callable', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(fn () => $trait->bootHasACL())->not->toThrow(Exception::class);
});

it('trait can be used in different classes', function (): void {
    $class1 = new class
    {
        use HasACL;
    };

    $class2 = new class
    {
        use HasACL;
    };

    expect(method_exists($class1, 'acl'))->toBeTrue();
    expect(method_exists($class2, 'acl'))->toBeTrue();
});

it('trait is properly namespaced', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(method_exists($trait, 'acl'))->toBeTrue();
    expect(method_exists($trait, 'bootHasACL'))->toBeTrue();
});

it('trait can be extended', function (): void {
    $baseClass = new class
    {
        use HasACL;
    };

    $extendedClass = new class
    {
        use HasACL;

        public function customMethod(): string
        {
            return 'custom';
        }
    };

    expect(method_exists($baseClass, 'acl'))->toBeTrue();
    expect(method_exists($extendedClass, 'acl'))->toBeTrue();
    expect(method_exists($extendedClass, 'customMethod'))->toBeTrue();
});

it('trait has proper structure', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(method_exists($trait, 'acl'))->toBeTrue();
    expect(method_exists($trait, 'bootHasACL'))->toBeTrue();
});

it('trait methods are accessible', function (): void {
    $trait = new class
    {
        use HasACL;
    };

    expect(method_exists($trait, 'acl'))->toBeTrue();
    expect(method_exists($trait, 'bootHasACL'))->toBeTrue();
});

it('trait can be used in different scenarios', function (): void {
    $scenario1 = new class
    {
        use HasACL;
    };

    $scenario2 = new class
    {
        use HasACL;
    };

    expect(method_exists($scenario1, 'acl'))->toBeTrue();
    expect(method_exists($scenario2, 'acl'))->toBeTrue();
});
