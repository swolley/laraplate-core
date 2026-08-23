<?php

declare(strict_types=1);

namespace Modules\Core\Import\Enums;

/**
 * The policy a relation field applies when a source token does not match any
 * existing related record by its natural key.
 */
enum OnMissingRelation: string
{
    /** Create the related record from the token (requires a create callback). */
    case Create = 'create';

    /** Silently drop the unmatched token. */
    case Skip = 'skip';

    /** Fail the row with a {@see \Modules\Core\Import\Exceptions\RowImportException}. */
    case Error = 'error';
}
