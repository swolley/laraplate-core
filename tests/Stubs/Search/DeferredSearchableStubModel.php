<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Traits\Searchable;

/**
 * Persisted searchable model whose engine is a shared recording double, so a
 * model reloaded by key (as a deferred flush does) still writes to the double.
 */
final class DeferredSearchableStubModel extends Model implements ISearchableModel
{
    use Searchable;

    public const string TABLE = 'core_deferred_search_stub_rows';

    public static ?RecordingSearchEngineStub $engine = null;

    public $timestamps = false;

    protected $table = self::TABLE;

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create(self::TABLE, static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
    }

    public static function dropTable(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    public function searchableAs(): string
    {
        return 'core_deferred_stub';
    }

    public function searchableUsing(): RecordingSearchEngineStub
    {
        return self::$engine ??= new RecordingSearchEngineStub;
    }
}
