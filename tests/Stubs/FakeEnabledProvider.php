<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Http\Request;
use Modules\Core\Auth\Contracts\IAuthenticationProvider;

class FakeEnabledProvider implements IAuthenticationProvider
{
    public bool $handleCalled = false;

    public function __construct(private readonly string $name) {}

    public function canHandle(Request $request): bool
    {
        return $request->attributes->get('provider') === $this->name;
    }

    public function authenticate(Request $request): array
    {
        $this->handleCalled = true;

        $user = new FakeAuthUser(['name' => 'John', 'email' => 'john@example.com']);

        return [
            'success' => true,
            'user' => $user,
            'error' => null,
            'license' => null,
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return $this->name;
    }
}

