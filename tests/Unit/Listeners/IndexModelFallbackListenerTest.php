<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;
use Laravel\Scout\Searchable;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Listeners\IndexModelFallbackListener;
use Modules\Core\Models\Setting;
use Modules\Core\Tests\LaravelTestCase;

uses(LaravelTestCase::class);

/**
 * Minimal model that uses Searchable so IndexInSearchJob constructor accepts it.
 * Used only to cover the listener dispatch branch.
 */
final class StubSearchableModel extends Model
{
    use Searchable;

    protected $table = 'settings';

    protected $guarded = [];

    public $incrementing = true;

    public function getKey()
    {
        return 1;
    }
}

it('does nothing when event is already handled', function (): void {
    Bus::fake();

    $setting = Setting::factory()->create();
    $event = new ModelRequiresIndexing($setting, false);
    $event->markAsHandled();

    (new IndexModelFallbackListener())->handle($event);

    Bus::assertNotDispatched(\Modules\Core\Search\Jobs\IndexInSearchJob::class);
});

it('dispatches IndexInSearchJob when not handled and sync is false', function (): void {
    Bus::fake();

    $model = new StubSearchableModel;
    $model->setAttribute('id', 1);
    $event = new ModelRequiresIndexing($model, false);

    (new IndexModelFallbackListener())->handle($event);

    Bus::assertDispatched(\Modules\Core\Search\Jobs\IndexInSearchJob::class);
});
