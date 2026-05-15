<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

enum SettingTypeEnum: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Float = 'float';
    case String = 'string';
    case Json = 'json';
    case Date = 'date';
}
