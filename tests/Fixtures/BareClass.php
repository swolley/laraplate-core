<?php

declare(strict_types=1);

namespace Tests\Fixtures;

/**
 * Minimal class without $hidden property anywhere in the hierarchy.
 * Used to test the defensive branch in createTranslationModel.
 */
class BareClass
{
    protected string $table = 'bare_items';
}
