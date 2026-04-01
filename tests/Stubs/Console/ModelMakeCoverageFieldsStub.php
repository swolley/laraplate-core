<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Console;

use Illuminate\Database\Eloquent\Model;

class ModelMakeCoverageFieldsStub extends Model
{
    protected $fillable = ['name'];

    protected $hidden = ['secret'];

    protected $attributes = ['status' => 'draft'];

    protected $appends = ['full_name'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
