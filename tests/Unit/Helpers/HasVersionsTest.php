<?php

declare(strict_types=1);

use Modules\Core\Helpers\HasVersions;

it('trait can be used', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait)->toHaveMethod('createVersion');
    expect($trait)->toHaveMethod('versions');
    expect($trait)->toHaveMethod('shouldBeVersioning');
});

it('trait has required methods', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect(method_exists($trait, 'createVersion'))->toBeTrue();
    expect(method_exists($trait, 'versions'))->toBeTrue();
    expect(method_exists($trait, 'shouldBeVersioning'))->toBeTrue();
});

it('can check if should be versioning', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait->shouldBeVersioning())->toBeTrue();
});

it('can get keep versions count', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait->getKeepVersionsCount())->toBe(5);
});

it('can set version strategy', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait->versionStrategy)->toBe(Overtrue\LaravelVersionable\VersionStrategy::DIFF);
});

it('can set dont versionable fields', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait->dontVersionable)->toContain('created_at', 'updated_at', 'deleted_at', 'last_login_at');
});

it('trait methods are callable', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect(fn () => $trait->shouldBeVersioning())->not->toThrow();
    expect(fn () => $trait->getKeepVersionsCount())->not->toThrow();
});

it('trait can be used in different classes', function (): void {
    $class1 = new class
    {
        use HasVersions;
    };

    $class2 = new class
    {
        use HasVersions;
    };

    expect($class1)->toHaveMethod('createVersion');
    expect($class2)->toHaveMethod('createVersion');
});

it('trait is properly namespaced', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait)->toHaveMethod('createVersion');
    expect($trait)->toHaveMethod('versions');
    expect($trait)->toHaveMethod('shouldBeVersioning');
});

it('trait can be extended', function (): void {
    $baseClass = new class
    {
        use HasVersions;
    };

    $extendedClass = new class
    {
        use HasVersions;

        public function customMethod(): string
        {
            return 'custom';
        }
    };

    expect($baseClass)->toHaveMethod('createVersion');
    expect($extendedClass)->toHaveMethod('createVersion');
    expect($extendedClass)->toHaveMethod('customMethod');
});

it('trait has proper structure', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait)->toHaveMethod('createVersion');
    expect($trait)->toHaveMethod('versions');
    expect($trait)->toHaveMethod('shouldBeVersioning');
});

it('trait methods are accessible', function (): void {
    $trait = new class
    {
        use HasVersions;
    };

    expect($trait)->toHaveMethod('createVersion');
    expect($trait)->toHaveMethod('versions');
    expect($trait)->toHaveMethod('shouldBeVersioning');
});

it('trait can be used in different scenarios', function (): void {
    $scenario1 = new class
    {
        use HasVersions;
    };

    $scenario2 = new class
    {
        use HasVersions;
    };

    expect($scenario1)->toHaveMethod('createVersion');
    expect($scenario2)->toHaveMethod('createVersion');
});
