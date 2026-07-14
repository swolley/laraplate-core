<?php

declare(strict_types=1);

namespace Modules\Core\Search\Enums;

enum SearchTokenKind: string
{
    case Numeric = 'numeric';
    case Uuid = 'uuid';
    case Email = 'email';
    case StructuredIdentifier = 'structured_identifier';
    case Acronym = 'acronym';
    case Short = 'short';
    case Word = 'word';

    public function protectedByDefault(): bool
    {
        return $this !== self::Word;
    }
}
