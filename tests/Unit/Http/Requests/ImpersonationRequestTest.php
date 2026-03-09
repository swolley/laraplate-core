<?php

declare(strict_types=1);

use Modules\Core\Http\Requests\ImpersonationRequest;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('returns validation rules with user required', function (): void {
    $request = new ImpersonationRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('user')
        ->and($rules['user'])->toContain('required');
});

it('authorize returns false when not authenticated', function (): void {
    $request = ImpersonationRequest::create('/', 'POST', ['user' => 1]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));

    expect($request->authorize())->toBeFalse();
});

