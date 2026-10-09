<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Modules\Core\Models\Media;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User as CoreUser;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Support\CrudApiExposure;
use Modules\Core\Support\PermissionName;
use Modules\Core\Tests\Stubs\Media\MediaProjectionOwnerStub;

/*
 * On the api guard a media is returned in a safe projection (spec 7.2): identifiers, descriptive values and URLs, never the raw
 * custom properties, the disks or the original file name. A caller holding `update` on media gets the full record, and the
 * web guard and a request with no user (console, queue, search indexing) are unchanged.
 */

/** The keys of the safe projection, in the order it returns them. */
const MEDIA_PROJECTION_KEYS = ['id', 'collection_name', 'mime_type', 'size', 'width', 'height', 'alt_text', 'url', 'conversions'];

beforeEach(function (): void {
    Cache::flush();
    CrudApiExposure::enable();
    Carbon::setTestNow('2026-10-09 10:00:00');
    Schema::create('media_projection_owners', function ($table): void {
        $table->id();
        $table->string('title')->nullable();
    });

    foreach (['select', 'update'] as $operation) {
        foreach (['web', 'api'] as $guard) {
            Permission::query()->firstOrCreate(['name' => PermissionName::forClass(Media::class, $operation), 'guard_name' => $guard]);
        }
    }
});

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * A claimed media owned by a user, with the descriptive values the projection exposes and the ones it must not.
 *
 * @param  array<string, mixed>  $attributes
 */
function mediaprojection_media(array $attributes = []): Media
{
    $owner = MediaProjectionOwnerStub::query()->create(['title' => 'Owner']);

    $media = new Media;
    $media->forceFill($attributes + [
        'collection_name' => 'images',
        'name' => 'sunrise',
        'file_name' => 'sunrise-original-name.jpg',
        'mime_type' => 'image/jpeg',
        'disk' => 'public',
        'conversions_disk' => 'public',
        'size' => 4321,
        'model_type' => $owner->getMorphClass(),
        'model_id' => $owner->getKey(),
        'custom_properties' => [
            'alt_text' => 'A sunrise over the sea',
            'description' => 'internal description',
            'keywords' => ['internal'],
            'content_hash' => 'secret-hash',
            'metadata' => ['width' => 800, 'height' => 600, 'make' => 'CameraCo'],
            '_provenance' => ['description' => 'human'],
        ],
        'manipulations' => [],
        'generated_conversions' => ['thumb-low' => true],
        'responsive_images' => [],
    ]);
    $media->saveQuietly();

    return $media->fresh();
}

/**
 * A user whose role on the given guard holds the select permission of media, and the update one when asked.
 */
function mediaprojection_user(string $guard, bool $with_update = false): User
{
    $role = Role::factory()->create(['name' => 'mediaprojection_' . uniqid(), 'guard_name' => $guard]);
    $role->givePermissionTo(Permission::query()->where(['name' => PermissionName::forClass(Media::class, 'select'), 'guard_name' => $guard])->firstOrFail());

    if ($with_update) {
        $role->givePermissionTo(Permission::query()->where(['name' => PermissionName::forClass(Media::class, 'update'), 'guard_name' => $guard])->firstOrFail());
    }

    $user = User::query()->findOrFail(CoreUser::factory()->create()->getKey());
    $user->assignRole($role);

    return $user->fresh();
}

/**
 * The bearer header of a personal token carrying the media permissions of the user's role as abilities.
 *
 * @return array<string, string>
 */
function mediaprojection_bearer(User $user): array
{
    $abilities = $user->getAllPermissions()->where('guard_name', 'api')->pluck('name')->all();

    return ['Authorization' => 'Bearer ' . $user->createToken('mediaprojection', $abilities)->plainTextToken];
}

function mediaprojection_act_on(string $guard, User $user): void
{
    Auth::shouldUse($guard);
    Auth::guard($guard)->setUser($user);
    request()->setUserResolver(static fn (): User => $user);
}

describe('on the api guard', function (): void {
    it('returns the safe projection to a caller without the update permission', function (): void {
        $media = mediaprojection_media();
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $array = $media->toArray();

        expect(array_keys($array))->toBe(MEDIA_PROJECTION_KEYS)
            ->and($array['id'])->toBe($media->id)
            ->and($array['collection_name'])->toBe('images')
            ->and($array['mime_type'])->toBe('image/jpeg')
            ->and($array['size'])->toBe(4321)
            ->and($array['width'])->toBe(800)
            ->and($array['height'])->toBe(600)
            ->and($array['alt_text'])->toBe('A sunrise over the sea')
            ->and($array['url'])->toBe($media->getUrl())
            ->and($array['conversions'])->toBe(['thumb-low' => $media->getUrl('thumb-low')]);
    });

    it('never returns the raw custom properties, the disks or the original file name', function (): void {
        $media = mediaprojection_media();
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $json = json_encode($media->toArray(), JSON_THROW_ON_ERROR);

        foreach (['custom_properties', 'conversions_disk', '"disk"', '"file_name"', 'secret-hash', 'internal description', 'CameraCo'] as $leak) {
            expect($json)->not->toContain($leak);
        }
    });

    it('gives dimensions and alternative text as null when the media has none', function (): void {
        $media = mediaprojection_media(['custom_properties' => []]);
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $array = $media->toArray();

        expect($array['width'])->toBeNull()
            ->and($array['height'])->toBeNull()
            ->and($array['alt_text'])->toBeNull();
    });

    it('returns the full record to a caller holding the update permission of media', function (): void {
        $media = mediaprojection_media();
        mediaprojection_act_on('api', mediaprojection_user('api', with_update: true));

        $array = $media->toArray();

        expect($array)->toHaveKeys(['custom_properties', 'disk', 'conversions_disk', 'file_name', 'uuid'])
            ->and($array['file_name'])->toBe('sunrise-original-name.jpg');
    });

    it('serves the projection through the select route', function (): void {
        $media = mediaprojection_media();

        $response = $this->getJson('/api/v1/select/core/media', mediaprojection_bearer(mediaprojection_user('api')));

        $response->assertOk();
        $row = collect($response->json('data'))->firstWhere('id', $media->id);

        expect(array_keys($row))->toBe(MEDIA_PROJECTION_KEYS)
            ->and($row['alt_text'])->toBe('A sunrise over the sea');
    });

    it('serves the full record through the select route to a caller holding the update permission', function (): void {
        $media = mediaprojection_media();

        $response = $this->getJson('/api/v1/select/core/media', mediaprojection_bearer(mediaprojection_user('api', with_update: true)));

        $row = collect($response->json('data'))->firstWhere('id', $media->id);

        expect($row)->toHaveKeys(['custom_properties', 'disk', 'file_name']);
    });
});

describe('the decision of the projection', function (): void {
    it('is taken once per request and user, however many media are serialized', function (): void {
        $first = mediaprojection_media();
        $second = mediaprojection_media();
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $this->partialMock(AuthorizationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('checkPermission')->once()->andReturn(false);
        });

        $first->toArray();
        $second->toArray();
        $first->toArray();
    });
});

describe('outside the api guard', function (): void {
    it('leaves the record unchanged on the web guard', function (): void {
        $media = mediaprojection_media();
        mediaprojection_act_on('web', mediaprojection_user('web'));

        expect($media->toArray())->toHaveKeys(['custom_properties', 'disk', 'conversions_disk', 'file_name', 'uuid']);
    });

    it('leaves the record unchanged when no user acts, as for the search index and the queue', function (): void {
        $media = mediaprojection_media();

        expect($media->toArray())->toHaveKeys(['custom_properties', 'disk', 'file_name']);
    });

    it('leaves the record of the app media route unchanged', function (): void {
        $media = mediaprojection_media();

        $response = $this->actingAs(mediaprojection_user('web'))->getJson('/app/crud/select/core/media');

        $row = collect($response->json('data'))->firstWhere('id', $media->id);

        expect($row)->toHaveKeys(['custom_properties', 'disk', 'file_name']);
    });
});

describe('the URLs of the projection', function (): void {
    it('are temporary signed URLs, valid for the configured minutes, on a disk that supports them', function (): void {
        Storage::fake('signed_disk');
        $media = mediaprojection_media(['disk' => 'signed_disk', 'conversions_disk' => 'signed_disk']);
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $array = $media->toArray();
        $expiration = '?expiration=' . now()->addMinutes(60)->getTimestamp();

        expect($array['url'])->toEndWith($expiration)
            ->and($array['conversions']['thumb-low'])->toEndWith($expiration);
    });

    it('follow the configured time to live', function (): void {
        config()->set('core.media.signed_url_ttl_minutes', 5);
        Storage::fake('signed_disk');
        $media = mediaprojection_media(['disk' => 'signed_disk', 'conversions_disk' => 'signed_disk']);
        mediaprojection_act_on('api', mediaprojection_user('api'));

        expect($media->toArray()['url'])->toEndWith('?expiration=' . now()->addMinutes(5)->getTimestamp());
    });

    it('are null on a local disk that is not public and cannot sign', function (): void {
        config()->set('filesystems.disks.private_local', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/private_local')]);
        $media = mediaprojection_media(['disk' => 'private_local', 'conversions_disk' => 'private_local']);
        mediaprojection_act_on('api', mediaprojection_user('api'));

        $array = $media->toArray();

        expect($array['url'])->toBeNull()
            ->and($array['conversions'])->toBe(['thumb-low' => null]);
    });

    it('are plain URLs on a public disk', function (): void {
        $media = mediaprojection_media();
        mediaprojection_act_on('api', mediaprojection_user('api'));

        expect($media->toArray()['url'])->toBe($media->getUrl())
            ->and($media->toArray()['url'])->toContain('/storage/');
    });
});
