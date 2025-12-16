<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Overtrue\LaravelVersionable\VersionStrategy;

final readonly class ModelVersioningRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $modelClass,
        public string|int|array|null $modelId,
        public ?string $connection,
        public string $table,
        public array $attributes,
        public array $replacements,
        public ?int $userId,
        public int $keepVersionsCount,
        public array $encryptedVersionable,
        public VersionStrategy|string|null $versionStrategy,
        public mixed $time = null,
    ) {}
}
