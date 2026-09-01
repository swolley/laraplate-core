<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasPath;

/**
 * @property string|null $slug
 */
final class HasPathStubModel extends Model
{
    use HasPath;

    public $timestamps = false;

    protected $table = 'has_path_stubs';

    protected $guarded = [];

    protected function getPath(): ?string
    {
        return 'root/child';
    }
}

it('keeps the path accessor available without appending it to every serialization', function (): void {
    $model = new HasPathStubModel(['id' => 7, 'slug' => 'leaf']);
    $model->exists = true;

    // On-demand: callers that already loaded a tree (or otherwise need the path) can
    // still read it. Serialization must not pay getPath() for every row in a list.
    expect($model->path)->toBe('has_path_stubs/root/child/leaf/7')
        ->and($model->toArray())->not->toHaveKey('path');
});
