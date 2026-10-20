<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

enum VersionSetKind: string
{
    case Change = 'change';
    case Revert = 'revert';

    /**
     * A complete anchor of the root: its creation, or a whole-aggregate baseline (D45, D53, D60).
     */
    case Baseline = 'baseline';
}
