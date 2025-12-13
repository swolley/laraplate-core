<?php

declare(strict_types=1);

use Modules\Core\Actions\Settings\GetVersionInfoAction;
use Modules\Core\Services\Docs\VersionService;
use Tests\TestCase;

final class GetVersionInfoActionTest extends TestCase
{
    public function test_returns_version_data(): void
    {
        $service = new class extends VersionService
        {
            public function getCurrentPackageVersion(): string
            {
                return '1.2.3';
            }

            public function getCurrentCommitHash(): ?string
            {
                return 'abc';
            }

            public function getCommitDate(?string $commitHash): ?string
            {
                return '2024-01-01 00:00:00';
            }
        };

        $action = new GetVersionInfoAction($service);

        $result = $action();

        $this->assertSame('1.2.3', $result['version']);
        $this->assertSame('abc', $result['commit']);
        $this->assertSame('2024-01-01 00:00:00', $result['date']);
    }
}

