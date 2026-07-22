<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Modules\Core\Enums\VersionSetKind;
use Modules\Core\Models\VersionSet;

final readonly class VersionSetOptions
{
    public function __construct(
        public VersionSetKind $kind = VersionSetKind::Change,
        public ?string $reason = null,
        public Model|int|null $actor = null,
        public VersionSet|int|null $revertedFrom = null,
    ) {
        if ($reason !== null && mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('A version set reason cannot exceed 255 characters.');
        }

        if ($kind === VersionSetKind::Revert && $revertedFrom === null) {
            throw new InvalidArgumentException('A revert version set must reference its target set.');
        }

        if ($kind === VersionSetKind::Change && $revertedFrom !== null) {
            throw new InvalidArgumentException('A change version set cannot reference a reverted set.');
        }

        $this->normalizePositiveId($actor, 'actor');
        $this->normalizePositiveId($revertedFrom, 'reverted-from set');
    }

    public function actorId(): ?int
    {
        return $this->normalizePositiveId($this->actor, 'actor');
    }

    public function revertedFromId(): ?int
    {
        return $this->normalizePositiveId($this->revertedFrom, 'reverted-from set');
    }

    private function normalizePositiveId(Model|int|null $value, string $label): ?int
    {
        if ($value === null) {
            return null;
        }

        $id = $value instanceof Model ? $value->getKey() : $value;

        if (! is_int($id) && (! is_string($id) || ! ctype_digit($id))) {
            throw new InvalidArgumentException("The version set {$label} must have an integer key.");
        }

        $normalized = (int) $id;

        if ($normalized < 1) {
            throw new InvalidArgumentException("The version set {$label} key must be positive.");
        }

        return $normalized;
    }
}
