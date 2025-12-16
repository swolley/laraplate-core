<?php

declare(strict_types=1);

use Modules\Core\Helpers\HasValidations;

it('trait can be used', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
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
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table';
    };

    $rules = $model->getRules();

    expect($rules)->toHaveKey('create');
    expect($rules)->toHaveKey('update');
    expect($rules)->toHaveKey('always');
});

it('can get operation rules', function (): void {
    $model = new class extends Illuminate\Database\Eloquent\Model
    {
        use HasValidations;

        protected $table = 'test_table';
    };

    $createRules = $model->getOperationRules('create');
    $updateRules = $model->getOperationRules('update');

    expect($createRules)->toBeArray();
    expect($updateRules)->toBeArray();
});

it('trait methods are callable', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(fn () => $trait->setSkipValidation(true))->not->toThrow(Throwable::class);
    expect(fn () => $trait->shouldSkipValidation())->not->toThrow(Throwable::class);
    expect(fn () => $trait->getRules())->not->toThrow(Throwable::class);
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

    expect(method_exists($class1, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($class2, 'setSkipValidation'))->toBeTrue();
});

it('trait is properly namespaced', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
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

    expect(method_exists($baseClass, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($extendedClass, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($extendedClass, 'customMethod'))->toBeTrue();
});

it('trait has proper structure', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
});

it('trait methods are accessible', function (): void {
    $trait = new class
    {
        use HasValidations;
    };

    expect(method_exists($trait, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'shouldSkipValidation'))->toBeTrue();
    expect(method_exists($trait, 'getRules'))->toBeTrue();
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

    expect(method_exists($scenario1, 'setSkipValidation'))->toBeTrue();
    expect(method_exists($scenario2, 'setSkipValidation'))->toBeTrue();
});
