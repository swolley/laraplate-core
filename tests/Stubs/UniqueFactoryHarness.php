<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Modules\Core\Helpers\HasUniqueFactoryValues;

final class UniqueFactoryHarness
{
    use HasUniqueFactoryValues;

    public function exposeUniqueValue(
        callable $fakerCall,
        ?string $modelClass = null,
        ?string $column = null,
        ?int $maxAttempts = null,
    ): string {
        return $this->uniqueValue($fakerCall, $modelClass, $column, $maxAttempts);
    }

    public function exposeUniqueSlug(
        string $name,
        ?string $modelClass = null,
        string $column = 'slug',
        ?int $maxAttempts = null,
    ): string {
        return $this->uniqueSlug($name, $modelClass, $column, $maxAttempts);
    }

    public function exposeUniqueEmail(
        ?string $modelClass = null,
        ?callable $fakerCall = null,
        ?string $column = 'email',
        ?int $maxAttempts = null,
    ): string {
        return $this->uniqueEmail($modelClass, $fakerCall, $column, $maxAttempts);
    }

    public function exposeUniquePhoneNumber(?string $modelClass = null, ?int $maxAttempts = null): string
    {
        return $this->uniquePhoneNumber($modelClass, $maxAttempts);
    }

    public function exposeUniqueUrl(?string $modelClass = null, ?int $maxAttempts = null): string
    {
        return $this->uniqueUrl($modelClass, $maxAttempts);
    }
}
