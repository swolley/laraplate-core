<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Overtrue\LaravelVersionable\VersionStrategy;

final class ModelVersioningRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $modelClass,
        public readonly string|int|array|null $modelId,
        public readonly ?string $connection,
        public readonly string $table,
        public readonly array $attributes,
        public readonly array $replacements,
        public readonly ?int $userId,
        public readonly int $keepVersionsCount,
        public readonly array $encryptedVersionable,
        public readonly VersionStrategy|string|null $versionStrategy,
        public readonly mixed $time = null,
    ) {}
}
