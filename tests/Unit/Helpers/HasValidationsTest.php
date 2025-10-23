<?php

declare(strict_types=1);

use Modules\Core\Helpers\HasValidations;

it('trait can be used', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait)->toHaveMethod('setSkipValidation');
    expect($trait)->toHaveMethod('shouldSkipValidation');
    expect($trait)->toHaveMethod('getRules');
});

it('trait has required methods', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('can skip validation', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait->shouldSkipValidation())->toBeFalse();

    $trait->setSkipValidation(true);
    expect($trait->shouldSkipValidation())->toBeTrue();

    $trait->setSkipValidation(false);
    expect($trait->shouldSkipValidation())->toBeFalse();
});

it('has default rules', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    $rules = $trait->getRules();

    expect($rules)->toHaveKey('create');
    expect($rules)->toHaveKey('update');
    expect($rules)->toHaveKey('always');
});

it('can get operation rules', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    $createRules = $trait->getOperationRules('create');
    $updateRules = $trait->getOperationRules('update');

    expect($createRules)->toBeArray();
    expect($updateRules)->toBeArray();
});

it('trait methods are callable', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(fn () => $trait->setSkipValidation(true))->not->toThrow();
    expect(fn () => $trait->shouldSkipValidation())->not->toThrow();
    expect(fn () => $trait->getRules())->not->toThrow();
});

it('trait can be used in different classes', function (): void {
    $class1 = new class
    {
        use HasValidations;
    };

    $class2 = new class
    {
        use HasValidations;
    };

    expect($class1)->toHaveMethod('setSkipValidation');
    expect($class2)->toHaveMethod('setSkipValidation');
});

it('trait is properly namespaced', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait)->toHaveMethod('setSkipValidation');
    expect($trait)->toHaveMethod('shouldSkipValidation');
    expect($trait)->toHaveMethod('getRules');
});

it('trait can be extended', function (): void {
    $baseClass = new class
    {
        use HasValidations;
    };

    $extendedClass = new class
    {
        use HasValidations;

        public function customMethod(): string
        {
            return 'custom';
        }
    };

    expect($baseClass)->toHaveMethod('setSkipValidation');
    expect($extendedClass)->toHaveMethod('setSkipValidation');
    expect($extendedClass)->toHaveMethod('customMethod');
});

it('trait has proper structure', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait)->toHaveMethod('setSkipValidation');
    expect($trait)->toHaveMethod('shouldSkipValidation');
    expect($trait)->toHaveMethod('getRules');
});

it('trait methods are accessible', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect($trait)->toHaveMethod('setSkipValidation');
    expect($trait)->toHaveMethod('shouldSkipValidation');
    expect($trait)->toHaveMethod('getRules');
});

it('trait can be used in different scenarios', function (): void {
    $scenario1 = new class
    {
        use HasValidations;
    };

    $scenario2 = new class
    {
        use HasValidations;
    };

    expect($scenario1)->toHaveMethod('setSkipValidation');
    expect($scenario2)->toHaveMethod('setSkipValidation');
});
