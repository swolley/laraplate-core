<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class SeedersRelationChildStubModel extends Model
{
    protected $table = 'seeders_relation_children';

    protected $fillable = ['name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SeedersRelationParentStubModel::class, 'parent_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            SeedersRelationTagStubModel::class,
            'seeders_relation_child_tag',
            'seeders_relation_child_id',
            'seeders_relation_tag_id',
        );
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
        $this->save();
    }
}
