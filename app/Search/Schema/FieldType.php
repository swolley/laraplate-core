<?php

declare(strict_types=1);

namespace Modules\Core\Search\Schema;

enum FieldType: string
{
    case TEXT = 'text';
    case KEYWORD = 'keyword';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
    case DATE = 'date';
    case VECTOR = 'vector';
    case ARRAY = 'array';
    case OBJECT = 'object';
}
