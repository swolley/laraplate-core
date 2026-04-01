<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Modules\Core\Models\User;

final class CreateUserPromptFlowStub extends User
{
    protected $table = 'users';

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'username', 'email', 'lang', 'password'];

    /**
     * @return array<string, mixed>
     */
    public function getOperationRules(?string $operation = null): array
    {
        return [
            'name' => ['required', 'string'],
            'username' => ['required', 'string'],
            'email' => ['required', 'email'],
            'lang' => 'in:active,inactive',
            'password' => ['nullable'],
        ];
    }
}
