<?php

declare(strict_types=1);

use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Versioning\Data\VersionSetOptions;

it('allows a reverted-from set only on a revert', function (VersionSetKind $kind): void {
    expect(fn () => new VersionSetOptions(kind: $kind, revertedFrom: 1))
        ->toThrow(InvalidArgumentException::class, 'Only a revert version set can reference a reverted set.');
})->with([
    'change' => [VersionSetKind::Change],
    'baseline' => [VersionSetKind::Baseline],
]);
