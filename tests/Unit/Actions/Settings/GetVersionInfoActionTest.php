<?php

declare(strict_types=1);

use Modules\Core\Actions\Settings\GetVersionInfoAction;
use Modules\Core\Services\Docs\VersionService;
use Tests\TestCase;

uses(TestCase::class);

it('returns version data', function (): void {
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

    expect($result['version'])->toBe('1.2.3');
    expect($result['commit'])->toBe('abc');
    expect($result['date'])->toBe('2024-01-01 00:00:00');
});
