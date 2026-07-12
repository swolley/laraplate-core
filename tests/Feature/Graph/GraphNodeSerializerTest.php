<?php

declare(strict_types=1);

use Modules\Core\Graph\GraphEntityResolver;
use Modules\Core\Graph\GraphNodeSerializer;
use Modules\Core\Graph\GraphProviderRegistry;
use Modules\Core\Models\User;

it('builds stable graph node ids from module entity and model key', function (): void {
    $user = new User();
    $user->forceFill(['id' => 7, 'name' => 'Graph User', 'email' => 'graph@example.test']);
    $user->exists = true;

    $serializer = new GraphNodeSerializer(new GraphEntityResolver(), new GraphProviderRegistry());

    $node = $serializer->serialize($user, 'summary');

    expect($node->id)->toBe('core:users:7');
    expect($node->module)->toBe('core');
    expect($node->entity)->toBe('users');
    expect($node->key)->toBe(7);
    expect($node->label)->toBe('Graph User');
    expect($node->attributes)->toHaveKey('name', 'Graph User');
    expect($node->attributes)->not->toHaveKey('password');
});
