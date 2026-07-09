<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;

it('resolves laravel validation lines from the default validation group', function (): void {
    app()->setLocale('it');

    $validator = Validator::make(['name' => ''], ['name' => 'required']);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->first('name'))->toContain('richiesto');
});

it('resolves module lang files through the default namespace using the filename as group', function (): void {
    app()->setLocale('it');

    expect(__('app.form.delete'))->toBe('Cancella');
    expect(__('pagination.overview', [
        'first' => 1,
        'last' => 10,
        'total' => 100,
        'seconds' => 2,
    ]))->toContain('risultati');
});

it('does not resolve legacy module-prefixed translation keys', function (): void {
    app()->setLocale('it');

    expect(__('core::app.form.delete'))->toBe('core::app.form.delete');
});
