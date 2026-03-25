<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasActivation;

class ActivationStubModel extends Model
{
    use HasActivation;

    protected $table = 'activation_stub';

    protected $fillable = ['name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
