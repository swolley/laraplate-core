<?php

declare(strict_types=1);

namespace Modules\Core\Overrides;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Modules\Core\Models\Concerns\HasPrefixedTableName;
use Modules\Core\Models\Concerns\HasValidations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\SoftDeletes\SoftDeletes;

/**
 * @mixin \Eloquent
 */
abstract class Model extends BaseModel
{
    use HasFactory;
    use HasPrefixedTableName;
    use HasValidations;
    use HasVersions;
    use SoftDeletes;

    // /**
    //  * Attribute payload used by {@see HasValidations::validateWithRules}. Overridden when
    //  * some assignable fields are delegated to relations until after validation (e.g. {@see HasPlace}).
    //  *
    //  * @return array<string, mixed>
    //  */
    // public function getAttributesForValidation(): array
    // {
    //     return $this->getAttributes();
    // }
}
