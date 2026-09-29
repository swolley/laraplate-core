<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Events\ModelRequiresIndexing;
use Modules\Core\Models\Media;
use Modules\Core\Models\ModelEmbedding;
use Modules\Core\Tests\Stubs\Search\MediaOwnerStubModel;

beforeEach(function (): void {
    config()->set('core.search.vector.enabled', false);
    Schema::create('media_owner_stubs', function ($table): void {
        $table->id();
        $table->string('title')->nullable();
    });
});

/**
 * @return array{0: MediaOwnerStubModel, 1: Media}
 */
function lifecycleOwnerWithMedia(): array
{
    $owner = MediaOwnerStubModel::query()->create(['title' => 'Article']);

    $media = new Media();
    $media->forceFill([
        'collection_name' => 'default',
        'name' => 'photo',
        'file_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'size' => 123,
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
        'custom_properties' => ['content_hash' => 'h1', 'description' => 'before'],
        'manipulations' => [],
        'generated_conversions' => [],
        'responsive_images' => [],
    ]);
    $media->saveQuietly();

    return [$owner, $media];
}

function assertOwnerReindexed(MediaOwnerStubModel $owner): void
{
    Event::assertDispatched(
        ModelRequiresIndexing::class,
        static fn (ModelRequiresIndexing $event): bool => $event->model instanceof MediaOwnerStubModel && $event->model->is($owner),
    );
}

it('reindexes the owner when a media display field is edited', function (): void {
    [$owner, $media] = lifecycleOwnerWithMedia();
    Event::fake([ModelRequiresIndexing::class]);

    $media->setCustomProperty('description', 'after')->save();

    assertOwnerReindexed($owner);
});

it('reindexes the owner when a media is soft-deleted and when it is restored', function (): void {
    [$owner, $media] = lifecycleOwnerWithMedia();

    Event::fake([ModelRequiresIndexing::class]);
    $media->delete();
    assertOwnerReindexed($owner);

    Event::fake([ModelRequiresIndexing::class]);
    Media::query()->withoutGlobalScopes()->whereKey($media->getKey())->firstOrFail()->restore();
    assertOwnerReindexed($owner);
});

it('drops the media embeddings and reindexes the owner on force delete', function (): void {
    [$owner, $media] = lifecycleOwnerWithMedia();
    $media->embeddings()->create(['embedding' => [0.1, 0.2], 'locale' => null, 'model_key' => 'k', 'content_hash' => 'x']);
    Event::fake([ModelRequiresIndexing::class]);

    $media->forceDelete();

    expect(ModelEmbedding::query()->where('model_type', $media->getMorphClass())->where('model_id', $media->getKey())->exists())->toBeFalse();
    assertOwnerReindexed($owner);
});

it('tolerates a media whose owner class no longer exists', function (): void {
    [, $media] = lifecycleOwnerWithMedia();
    Media::query()->whereKey($media->getKey())->update(['model_type' => 'Modules\\Disabled\\Models\\Gone']);

    Media::query()->findOrFail($media->getKey())->forceDelete();

    expect(Media::query()->withoutGlobalScopes()->whereKey($media->getKey())->exists())->toBeFalse();
});
