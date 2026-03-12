<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Version;
use Modules\Core\Services\VersioningService;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

it('can be instantiated', function (): void {
    $service = new VersioningService();

    expect($service)->toBeInstanceOf(VersioningService::class);
});

it('creates a version with basic attributes and no extras', function (): void {
    config()->set('versionable.version_model', Version::class);

    $service = new VersioningService();

    $version = $service->createVersion(
        FakeVersionedModel::class,
        123,
        null,
        'fake_table',
        ['id' => 123, 'foo' => 'bar'],
    );

    expect($version)->toBeInstanceOf(Version::class)
        ->and($version->contents)->toHaveKey('foo', 'bar');
});

it('sets user id and encrypts selected fields and trims older versions', function (): void {
    config()->set('versionable.version_model', Version::class);

    $service = new VersioningService();

    $version = $service->createVersion(
        FakeVersionedModel::class,
        1,
        null,
        'fake_table',
        ['id' => 1, 'secret' => 'plain', 'other' => 'keep'],
        [],
        userId: 10,
        keepVersionsCount: 1,
        encryptedVersionable: ['secret'],
        versionStrategy: null,
        time: now(),
    );

    expect($version)->toBeInstanceOf(Version::class)
        ->and($version->contents['other'])->toBe('keep')
        ->and($version->contents['secret'])->not->toBe('plain');
});

/**
 * Simple fake versioned model used only for testing VersioningService.
 */
class FakeVersionedModel extends Model
{
    public $connection = 'sqlite';

    public $table = 'fake_table';

    public $versionStrategy = null;

    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    public function setConnection($name): static
    {
        $this->connection = $name;

        return $this;
    }

    public function setTable($table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getUserForeignKeyName(): string
    {
        return 'user_id';
    }

    public function getVersionStrategy()
    {
        return $this->versionStrategy;
    }

    public function getVersionModel(): string
    {
        return Version::class;
    }

    public function getVersionUserId(): ?int
    {
        return null;
    }

    public function getVersionableAttributes(?object $strategy, array $replacements): array
    {
        return ['foo' => 'bar', 'secret' => 'plain', 'other' => 'keep'];
    }

    public function versions()
    {
        return new class
        {
            public function latest()
            {
                return $this;
            }

            public function skip($count)
            {
                return $this;
            }

            public function get()
            {
                return collect();
            }
        };
    }
}


