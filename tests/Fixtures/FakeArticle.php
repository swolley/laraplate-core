<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasTranslations;

class FakeArticle extends Model
{
    use HasTranslations;
    protected $table = 'articles';

    protected $fillable = [
        'slug',
        'body',
        'is_published',
    ];

    protected $hidden = [
        'body',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'string',
            'is_published' => 'boolean',
        ];
    }
}
