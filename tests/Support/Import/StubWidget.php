<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * A throwaway Eloquent model used only to exercise the generic import framework
 * against a real table. Its `stub_widgets` table is created ad hoc by the tests
 * that use it, so the framework can be proven end-to-end without depending on any
 * concrete domain entity.
 *
 * @property string $code
 * @property string $name
 */
final class StubWidget extends Model
{
    protected $table = 'stub_widgets';

    /**
     * @var list<string>
     */
    protected $fillable = ['code', 'name'];
}
