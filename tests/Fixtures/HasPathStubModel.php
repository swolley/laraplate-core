<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasPath;

/**
 * @property string|null $slug
 */
final class HasPathStubModel extends Model
{
    use HasPath;

    public $timestamps = false;

    protected $table = 'has_path_stubs';

    protected $guarded = [];

    protected function getPath(): ?string
    {
        return 'root/child';
    }
}