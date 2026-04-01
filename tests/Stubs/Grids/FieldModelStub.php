<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Grids;

use \Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Traits\HasGridUtils;

final class FieldModelStub extends Model
{
    use HasGridUtils;

    protected $table = 'users';

    protected $fillable = ['name', 'email'];

    protected $hidden = ['password'];

    protected $appends = ['computed_name'];

    public function getComputedNameAttribute(): string
    {
        return 'computed';
    }

    public function setComputedNameAttribute(string $value): void
    {
        $this->attributes['computed_name'] = $value;
    }

    public function getRules(): array
    {
        return [
            'email' => ['required', 'email'],
            'name' => 'required|string',
        ];
    }

    protected function casts(): array
    {
        return [];
    }
}
