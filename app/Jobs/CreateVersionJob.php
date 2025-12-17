<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Services\VersioningService;
use Overtrue\LaravelVersionable\VersionStrategy;

final class CreateVersionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public int $timeout = 120;

    public function __construct(
        private readonly string $modelClass,
        private readonly string|int|array|null $modelId,
        private readonly ?string $modelConnection,
        private readonly string $table,
        private readonly array $attributes,
        private readonly array $replacements = [],
        private readonly ?int $userId = null,
        private readonly int $keepVersionsCount = 0,
        private readonly array $encryptedVersionable = [],
        private readonly VersionStrategy|string|null $versionStrategy = null,
        private readonly mixed $time = null,
    ) {
        $this->onQueue('versions');
    }

    public function middleware(): array
    {
        return [
            new RateLimited('versions'),
        ];
    }

    public function handle(VersioningService $versioningService): void
    {
        $versioningService->createVersion(
            modelClass: $this->modelClass,
            modelId: $this->modelId,
            connection: $this->modelConnection,
            table: $this->table,
            attributes: $this->attributes,
            replacements: $this->replacements,
            userId: $this->userId,
            keepVersionsCount: $this->keepVersionsCount,
            encryptedVersionable: $this->encryptedVersionable,
            versionStrategy: $this->versionStrategy,
            time: $this->time,
        );
    }
}
