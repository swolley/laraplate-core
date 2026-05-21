<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\User;
use Modules\Core\Models\UserGridConfig;


it('user relation points to configured user class', function (): void {
    $config = new UserGridConfig();
    $relation = $config->user();

    expect($relation->getRelated())->toBeInstanceOf(User::class);
});

it('getRules returns expected validation keys for grid config', function (): void {
    $rules = (new UserGridConfig())->getRules();

    expect($rules)->toHaveKey(UserGridConfig::DEFAULT_RULE)
        ->and($rules[UserGridConfig::DEFAULT_RULE])->toHaveKey('user_id')
        ->and($rules[UserGridConfig::DEFAULT_RULE])->toHaveKey('grid_name')
        ->and($rules[UserGridConfig::DEFAULT_RULE])->toHaveKey('layout_name')
        ->and($rules[UserGridConfig::DEFAULT_RULE])->toHaveKey('is_public')
        ->and($rules[UserGridConfig::DEFAULT_RULE])->toHaveKey('config');
});

it('casts include expected primitive and json mappings', function (): void {
    $casts = (new ReflectionMethod(UserGridConfig::class, 'casts'))->invoke(new UserGridConfig());

    expect($casts)->toMatchArray([
        'user_id' => 'integer',
        'is_public' => 'boolean',
        'config' => 'json',
    ]);
});
