<?php

declare(strict_types=1);

use Modules\Core\Helpers\HasCrudOperations;


it('normalize_path uses directory separators', function (): void {
    expect(normalize_path('foo/bar\\baz'))->toContain(DIRECTORY_SEPARATOR);
});

it('array_sort_keys sorts keys recursively', function (): void {
    $sorted = array_sort_keys(['z' => 1, 'a' => ['m' => 2, 'b' => 3]]);

    expect(array_keys($sorted))->toBe(['a', 'z'])
        ->and(array_keys($sorted['a']))->toBe(['b', 'm']);
});

it('class_uses_trait detects traits on class', function (): void {
    $with_trait = new class
    {
        use HasCrudOperations;
    };

    expect(class_uses_trait($with_trait, HasCrudOperations::class))->toBeTrue()
        ->and(class_uses_trait(new stdClass, HasCrudOperations::class))->toBeFalse();
});

it('is_json validates json strings', function (): void {
    expect(is_json('{"a":1}'))->toBeTrue()
        ->and(is_json('not json'))->toBeFalse();
});

it('modules returns an array without throwing', function (): void {
    expect(modules())->toBeArray();
});

it('preview toggles session flag', function (): void {
    preview(enablePreview: false);
    expect(preview())->toBeFalse();

    preview(enablePreview: true);
    expect(preview())->toBeTrue();

    preview(enablePreview: false);
});
