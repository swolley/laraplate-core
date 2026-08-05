<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Modules\Core\Versioning\Data\RelationDescriptor;

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
}
