<?php

declare(strict_types=1);

namespace Modules\Fake\Models;

use Illuminate\Database\Eloquent\Model;

class FakeModulePost extends Model
{
    protected $table = 'posts';

    protected $fillable = [
        'title',
        'slug',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'string',
            'content' => 'string',
        ];
    }
}
