<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Closure;
use InvalidArgumentException;
use Modules\Core\Enums\VersionChangeType;
use Modules\Core\Versioning\Contracts\VersionSetManagerInterface;
use Modules\Core\Versioning\Contracts\VersionWriterInterface;
use Modules\Core\Versioning\Data\RelationDescriptor;
use Modules\Core\Versioning\Data\VersionChange;
use Modules\Core\Versioning\Data\VersionSetOptions;
use Modules\Core\Versioning\Data\VersionSetRoot;
use Overtrue\LaravelVersionable\VersionStrategy;

/**
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasVersionedRelations
{
    /**
     * @return list<RelationDescriptor>
     */
    protected function versionedRelations(): array
    {
        return [];
    }

    public function versionedRelationDescriptor(string $relation): ?RelationDescriptor
    {
        foreach ($this->versionedRelations() as $descriptor) {
            if ($descriptor->relation === $relation) {
                return $descriptor;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    public function attachVersioned(string $relation, int|string $subjectId, array $pivot = []): void
    {
        $this->recordRelationMembership($relation, $subjectId, VersionChangeType::Created, $pivot, function () use ($relation, $subjectId, $pivot): void {
            // Idempotent: a re-attach updates the pivot in place instead of inserting a duplicate row,
            // keeping the live relation in step with the reconstructed membership.
            $this->{$relation}()->syncWithoutDetaching([$subjectId => $pivot]);
        });
    }

    public function detachVersioned(string $relation, int|string $subjectId): void
    {
        $this->recordRelationMembership($relation, $subjectId, VersionChangeType::Deleted, [], function () use ($relation, $subjectId): void {
            $this->{$relation}()->detach($subjectId);
        });
    }

    /**
     * Replace the whole membership of a versioned relation with a target set, as a single revision:
     * every detach and upsert joins one version set. The target accepts either a list of subject ids
     * or a `[subjectId => pivotAttributes]` map.
     *
     * @param  array<int|string, array<string, mixed>>|list<int|string>  $target
     */
    public function syncVersioned(string $relation, array $target): void
    {
        $this->assertVersionedRelation($relation);

        $normalized = $this->normalizeSyncTarget($target);
        $current_ids = array_map(
            static fn (array $entry): int|string => $entry['id'],
            $this->versionedRelationMembership($relation),
        );
        $to_detach = array_diff($current_ids, array_keys($normalized));

        $this->runInMembershipScope(function () use ($relation, $normalized, $to_detach): void {
            foreach ($to_detach as $subject_id) {
                $this->{$relation}()->detach($subject_id);
                $this->writeMembershipRow($relation, $subject_id, VersionChangeType::Deleted, []);
            }

            foreach ($normalized as $subject_id => $pivot) {
                $this->{$relation}()->syncWithoutDetaching([$subject_id => $pivot]);
                $this->writeMembershipRow($relation, $subject_id, VersionChangeType::Created, $pivot);
            }
        });
    }

    /**
     * Current membership of a versioned relation, reconstructed by replaying its membership
     * version rows in revision order: a `Created` event adds/updates the subject, a `Deleted`
     * event removes it.
     *
     * @return list<array{id: int|string, pivot: array<string, mixed>}>
     */
    public function versionedRelationMembership(string $relation): array
    {
        $events = $this->versions()
            ->where('relation_path', $relation)
            ->orderBy('version_set_id')
            ->orderBy('sequence')
            ->get();

        $membership = [];

        foreach ($events as $event) {
            $id = $event->subject_key['id'] ?? null;

            if ($id === null) {
                continue;
            }

            if ($event->change_type === VersionChangeType::Deleted) {
                unset($membership[$id]);

                continue;
            }

            $membership[$id] = ['id' => $id, 'pivot' => $event->contents ?? []];
        }

        return array_values($membership);
    }

    /**
     * One supported membership mutation = one version set = one revision. The mutation and its
     * history row commit together; the membership row identifies the subject only by `subjectKey`,
     * so it is stored as a self-contained SNAPSHOT.
     *
     * @param  array<string, mixed>  $pivot
     */
    private function recordRelationMembership(string $relation, int|string $subjectId, VersionChangeType $type, array $pivot, Closure $mutation): void
    {
        $this->assertVersionedRelation($relation);

        $this->runInMembershipScope(function () use ($mutation, $relation, $subjectId, $type, $pivot): void {
            $mutation();
            $this->writeMembershipRow($relation, $subjectId, $type, $pivot);
        });
    }

    private function assertVersionedRelation(string $relation): void
    {
        if ($this->versionedRelationDescriptor($relation) === null) {
            throw new InvalidArgumentException("Relation '{$relation}' is not a declared versioned relation.");
        }
    }

    private function runInMembershipScope(Closure $operation): void
    {
        app(VersionSetManagerInterface::class)->run(
            VersionSetRoot::forModel($this),
            $operation,
            new VersionSetOptions(actor: $this->versionActor()),
        );
    }

    /**
     * @param  array<string, mixed>  $pivot
     */
    private function writeMembershipRow(string $relation, int|string $subjectId, VersionChangeType $type, array $pivot): void
    {
        resolve(VersionWriterInterface::class)->write(new VersionChange(
            model: $this,
            type: $type,
            originalContents: [],
            contents: $type === VersionChangeType::Created ? $pivot : [],
            strategy: VersionStrategy::SNAPSHOT,
            userId: $this->versionActor(),
            relationPath: $relation,
            subjectKey: ['id' => $subjectId],
        ));
    }

    private function versionActor(): ?int
    {
        if (! method_exists($this, 'getVersionUserId')) {
            return null;
        }

        $actor = $this->getVersionUserId();

        return is_numeric($actor) ? (int) $actor : null;
    }

    /**
     * @param  array<int|string, array<string, mixed>>|list<int|string>  $target
     * @return array<int|string, array<string, mixed>>
     */
    private function normalizeSyncTarget(array $target): array
    {
        $normalized = [];

        foreach ($target as $key => $value) {
            if (is_int($key) && ! is_array($value)) {
                $normalized[$value] = [];

                continue;
            }

            $normalized[$key] = is_array($value) ? $value : [];
        }

        return $normalized;
    }
}
