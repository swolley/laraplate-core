<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

/**
 * A translatable model that declares `translation_fallback_enabled` off, so the declaration wins
 * over the default of on.
 */
final class FallbackDeclaringTranslatableModel extends FakeTranslatableModel
{
    protected bool $translation_fallback_enabled = false;
}
