<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Version;

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
