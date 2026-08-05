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
        if ($this->versionedRelationDescriptor($relation) === null) {
            throw new InvalidArgumentException("Relation '{$relation}' is not a declared versioned relation.");
        }

        $actor = method_exists($this, 'getVersionUserId') ? $this->getVersionUserId() : null;

        app(VersionSetManagerInterface::class)->run(
            VersionSetRoot::forModel($this),
            function () use ($mutation, $type, $relation, $subjectId, $pivot, $actor): void {
                $mutation();

                resolve(VersionWriterInterface::class)->write(new VersionChange(
                    model: $this,
                    type: $type,
                    originalContents: [],
                    contents: $type === VersionChangeType::Created ? $pivot : [],
                    strategy: VersionStrategy::SNAPSHOT,
                    userId: $actor,
                    relationPath: $relation,
                    subjectKey: ['id' => $subjectId],
                ));
            },
            new VersionSetOptions(actor: $actor),
        );
    }
}
