<?php

declare(strict_types=1);

namespace Modules\Core\Helpers;

/**
 * The content locale a reader picked for the current request, read through {@see LocaleContext}. Bound as a
 * scoped instance, so it lives as long as the request or the job and a long-running worker never carries it
 * over; and, unlike Laravel's Context, it does not travel into the jobs the request dispatches.
 */
final class ContentLocale
{
    public ?string $locale = null;
}
