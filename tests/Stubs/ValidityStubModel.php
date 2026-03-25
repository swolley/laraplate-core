<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidity;

class ValidityStubModel extends Model
{
    use HasValidity;

    protected $table = 'validity_stub';

    protected $fillable = ['name', 'valid_from', 'valid_to'];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
    ];
}
