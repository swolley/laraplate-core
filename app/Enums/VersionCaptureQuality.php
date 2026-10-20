<?php

declare(strict_types=1);

namespace Modules\Core\Enums;

/**
 * Whether the business write and its history committed together (D47, D52).
 */
enum VersionCaptureQuality: string
{
    case Atomic = 'atomic';
    case BestEffort = 'best_effort';
    case Incomplete = 'incomplete';
}
