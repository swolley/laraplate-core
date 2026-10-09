<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;

/**
 * A model on the users table that declares one computed method for the CRUD: the only kind of method a request
 * may invoke as a `method` column.
 */
final class ComputedMethodUserStub extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    public function shoutedName(): string
    {
        return mb_strtoupper((string) $this->getAttribute('name'));
    }

    /**
     * @return array<string, array{columns?: list<string>, relations?: list<string>}>
     */
    public function crudComputedDependencies(): array
    {
        return [
            'shoutedName' => ['columns' => ['name']],
        ];
    }
}
