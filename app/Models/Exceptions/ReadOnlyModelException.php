<?php

declare(strict_types=1);

namespace Modules\Core\Models\Exceptions;

use LogicException;

final class ReadOnlyModelException extends LogicException {}
