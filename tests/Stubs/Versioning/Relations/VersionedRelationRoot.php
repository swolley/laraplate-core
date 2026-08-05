<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Versioning\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Core\Enums\RelationOwnership;
use Modules\Core\Models\Concerns\HasVersionedRelations;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Versioning\Data\RelationDescriptor;
use Overtrue\LaravelVersionable\VersionStrategy;

final class VersionedRelationRoot extends Model
{
    use HasVersionedRelations;
    use HasVersions;

    public const string TABLE = 'core_test_versioned_relation_roots';

    public const string PIVOT_TABLE = 'core_test_versioned_relation_pivot';

    protected $table = self::TABLE;

    protected $guarded = [];

    protected array $versionable = ['title'];

    protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;

    /**
     * @return BelongsToMany<VersionedRelationSubject, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            VersionedRelationSubject::class,
            self::PIVOT_TABLE,
            'root_id',
            'subject_id',
        )->withPivot('position');
    }

    /**
     * @return list<RelationDescriptor>
     */
    protected function versionedRelations(): array
    {
        return [
            new RelationDescriptor('categories', RelationOwnership::Reference),
        ];
    }
}
