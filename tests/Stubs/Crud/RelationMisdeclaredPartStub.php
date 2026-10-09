<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Crud;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IsPartOfParent;
use Override;

/**
 * A part whose declared parent relation is not a relation: reading it must fail, never pass.
 */
final class RelationMisdeclaredPartStub extends Model implements IsPartOfParent
{
    protected $table = 'relauth_misdeclared_parts';

    #[Override]
    public function parentRelation(): string
    {
        return 'truncate';
    }
}
