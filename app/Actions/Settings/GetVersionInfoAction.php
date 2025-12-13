<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Settings;

use Modules\Core\Services\Docs\VersionService;

final class GetVersionInfoAction
{
    public function __construct(private readonly VersionService $versionService)
    {
    }

    public function __invoke(): array
    {
        $hash = $this->versionService->getCurrentCommitHash();

        return [
            'version' => $this->versionService->getCurrentPackageVersion(),
            'commit' => $hash,
            'date' => $this->versionService->getCommitDate($hash),
        ];
    }
}

