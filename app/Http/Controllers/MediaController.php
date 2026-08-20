<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\MediaClaimRequest;
use Modules\Core\Http\Requests\MediaDeleteRequest;
use Modules\Core\Http\Requests\MediaListRequest;
use Modules\Core\Http\Requests\MediaPendingDeleteRequest;
use Modules\Core\Http\Requests\MediaPendingListRequest;
use Modules\Core\Http\Requests\MediaPendingUploadRequest;
use Modules\Core\Http\Requests\MediaRequest;
use Modules\Core\Http\Requests\MediaUploadRequest;
use Modules\Core\Http\Resources\MediaResource;
use Modules\Core\Models\Media;
use Modules\Core\Models\MediaDraft;
use Modules\Core\Services\Authorization\AuthorizationService;
use Modules\Core\Services\Crud\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Generic media HTTP API for any media-enabled owner entity.
 *
 * Per-id endpoints are addressed as `{module}/{entity}/{id}` and reuse the owner
 * entity's CRUD ACL permissions: LIST requires `select`, UPLOAD and DELETE
 * require `update`. The owner record is resolved through the caller's row-level
 * ACL filters, so a row hidden by ACL surfaces as a 404.
 *
 * A token-keyed pending bucket ({@see MediaDraft}) backs CREATE forms, where the
 * owner record does not exist yet: uploads are staged against the `insert`
 * permission and later moved onto the freshly created record by {@see claim()}
 * (gated by `update` plus the target row's ACL).
 */
final class MediaController extends Controller
{
    private const string DEFAULT_CLAIM_COLLECTION = 'images';

    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly QueryBuilder $queryBuilder,
    ) {
        parent::__construct();
    }

    /**
     * List the owner record's media grouped by collection name.
     */
    public function list(MediaListRequest $request): Response
    {
        return $this->handle($request, function (string $permissionName) use ($request): Response {
            $record = $this->resolveOwnerRecord($request, $permissionName);

            return $this->groupedResponse(
                $request,
                $this->mediaQuery($record)->get(),
                static fn (Media $media): string => (string) $media->collection_name,
            );
        });
    }

    /**
     * Add an uploaded file to one of the owner model's registered collections.
     */
    public function upload(MediaUploadRequest $request): Response
    {
        return $this->handle($request, function (string $permissionName) use ($request): Response {
            $record = $this->resolveOwnerRecord($request, $permissionName);

            /** @var UploadedFile $file */
            $file = $request->file('file');

            $custom_properties = $request->input('custom_properties');
            $media = $this->addUploadedFile(
                $record,
                $file,
                $request->string('collection')->toString(),
                $request->string('name')->toString(),
                is_array($custom_properties) ? $custom_properties : [],
            );

            return new ResponseBuilder($request)
                ->setData(new MediaResource($media))
                ->setStatus(Response::HTTP_CREATED)
                ->json();
        });
    }

    /**
     * Delete a single media item (by id or uuid) belonging to the owner record.
     */
    public function delete(MediaDeleteRequest $request): Response
    {
        return $this->handle($request, function (string $permissionName) use ($request): Response {
            $record = $this->resolveOwnerRecord($request, $permissionName);
            $identifier = (string) $request->route('media');

            $media = $this->mediaQuery($record)
                ->where(static function (Builder $query) use ($identifier): void {
                    $query->where('id', $identifier)->orWhere('uuid', $identifier);
                })
                ->first();

            throw_unless($media instanceof Media, new NotFoundHttpException('Media not found for this entity.'));

            $key = $media->getKey();
            $media->delete();

            return new ResponseBuilder($request)->setData(['deleted' => true, 'id' => $key])->json();
        });
    }

    /**
     * Stage an uploaded file in the current user's pending bucket for a token,
     * before the owner record exists. Gated by the entity's `insert` permission.
     */
    public function uploadPending(MediaPendingUploadRequest $request): Response
    {
        return $this->handle($request, function () use ($request): Response {
            $draft = MediaDraft::query()->firstOrCreate($this->draftAttributes($request));

            /** @var UploadedFile $file */
            $file = $request->file('file');

            $collection = $request->string('collection')->toString();
            $custom_properties = $request->input('custom_properties');
            $properties = is_array($custom_properties) ? $custom_properties : [];
            $properties['target_collection'] = $collection;

            $media = $this->addUploadedFile(
                $draft,
                $file,
                MediaDraft::PENDING_COLLECTION,
                $request->string('name')->toString(),
                $properties,
            );

            return new ResponseBuilder($request)
                ->setData(new MediaResource($media))
                ->setStatus(Response::HTTP_CREATED)
                ->json();
        });
    }

    /**
     * List the current user's pending media for a token, grouped by the stored
     * `target_collection` custom property. Gated by the entity's `insert` permission.
     */
    public function listPending(MediaPendingListRequest $request): Response
    {
        return $this->handle($request, function () use ($request): Response {
            $draft = MediaDraft::query()->where($this->draftAttributes($request))->first();

            $media = $draft instanceof MediaDraft ? $this->pendingMedia($draft) : new Collection;

            return $this->groupedResponse($request, $media, $this->targetCollectionResolver());
        });
    }

    /**
     * Delete a single pending media item from the current user's bucket for a token.
     * Gated by the entity's `insert` permission.
     */
    public function deletePending(MediaPendingDeleteRequest $request): Response
    {
        return $this->handle($request, function () use ($request): Response {
            $draft = MediaDraft::query()->where($this->draftAttributes($request))->first();

            throw_unless($draft instanceof MediaDraft, new NotFoundHttpException('Pending media not found.'));

            $identifier = (string) $request->route('media');

            $media = $draft->media()
                ->where(static function (Builder $query) use ($identifier): void {
                    $query->where('id', $identifier)->orWhere('uuid', $identifier);
                })
                ->first();

            throw_unless($media instanceof Media, new NotFoundHttpException('Pending media not found.'));

            $key = $media->getKey();
            $media->delete();

            return new ResponseBuilder($request)->setData(['deleted' => true, 'id' => $key])->json();
        });
    }

    /**
     * Move the current user's pending media for a token onto the freshly created
     * owner record, then drop the emptied draft. Gated by the entity's `update`
     * permission and the target row's ACL. A missing/foreign token moves nothing.
     */
    public function claim(MediaClaimRequest $request): Response
    {
        return $this->handle($request, function (string $permissionName) use ($request): Response {
            $record = $this->resolveOwnerRecord($request, $permissionName);
            $draft = MediaDraft::query()->where($this->draftAttributes($request))->first();

            $moved = new Collection;

            if ($draft instanceof MediaDraft) {
                foreach ($this->pendingMedia($draft) as $media) {
                    $target = $media->getCustomProperty('target_collection');
                    $collection = is_string($target) && $target !== '' ? $target : self::DEFAULT_CLAIM_COLLECTION;
                    $moved->push($media->move($record, $collection));
                }

                $draft->delete();
            }

            return $this->groupedResponse(
                $request,
                $moved,
                static fn (Media $media): string => (string) $media->collection_name,
            );
        });
    }

    /**
     * Enforce the owner entity's CRUD permission, then run the endpoint body,
     * mapping authorization and not-found failures onto the shared envelope. The
     * checked permission name is passed to the callback for ACL resolution.
     */
    private function handle(MediaRequest $request, Closure $callback): Response
    {
        try {
            $model = $request->mediaModel();

            $permission_name = $this->authorizationService->ensurePermission(
                $request,
                $model->getTable(),
                $request->mediaOperation(),
                $model->getConnectionName(),
            );

            return $callback($permission_name);
        } catch (AuthorizationException $exception) {
            return $this->errorResponse($request, $exception, Response::HTTP_UNAUTHORIZED);
        } catch (ModelNotFoundException|NotFoundHttpException $exception) {
            return $this->errorResponse($request, $exception, Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Load the owner record for the `{id}` route parameter through the caller's
     * row-level ACL filters. A row hidden by ACL yields no match and surfaces as a
     * {@see ModelNotFoundException} (mapped to a 404). Unrestricted callers
     * (`getAclFilters()` returns null) resolve the record exactly as a plain lookup.
     */
    private function resolveOwnerRecord(MediaRequest $request, string $permissionName): Model
    {
        $query = $request->mediaModel()->newQuery()->whereKey($request->route('id'));

        $filters = $this->authorizationService->getAclFilters($permissionName);

        if ($filters instanceof FiltersGroup) {
            $query->where(function (Builder $inner) use ($filters): void {
                $this->queryBuilder->applyFilters($inner, $filters);
            });
        }

        return $query->firstOrFail();
    }

    /**
     * Add an uploaded file to a media-enabled record's collection, honoring an
     * optional custom file name and custom properties.
     *
     * @param  array<string, mixed>  $customProperties
     */
    private function addUploadedFile(Model $record, UploadedFile $file, string $collection, string $name, array $customProperties): Media
    {
        /** @phpstan-ignore method.notFound */
        $adder = $record->addMedia($file->getRealPath())
            ->usingFileName($name !== '' ? $name : $file->getClientOriginalName());

        if ($name !== '') {
            $adder->usingName($name);
        }

        if ($customProperties !== []) {
            $adder->withCustomProperties($customProperties);
        }

        return $adder->toMediaCollection($collection);

        /** @var Media $media */
    }

    /**
     * Group a media collection by a caller-supplied key and render the shared envelope.
     *
     * @param  Collection<int, Media>  $media
     * @param  callable(Media): string  $keyBy
     */
    private function groupedResponse(MediaRequest $request, Collection $media, callable $keyBy): Response
    {
        $grouped = $media
            ->groupBy($keyBy)
            ->map(fn (Collection $items): array => MediaResource::collection($items->values())->resolve($request))
            ->toArray();

        return new ResponseBuilder($request)->setData($grouped)->json();
    }

    /**
     * Group pending media by their stored `target_collection`, falling back to the
     * `pending` bucket when the property is absent.
     *
     * @return callable(Media): string
     */
    private function targetCollectionResolver(): callable
    {
        return static function (Media $media): string {
            $target = $media->getCustomProperty('target_collection');

            return is_string($target) && $target !== '' ? $target : MediaDraft::PENDING_COLLECTION;
        };
    }

    /**
     * The draft-identifying attributes for the current user, token and target entity.
     *
     * @return array{user_id: mixed, token: string, target_module: string, target_entity: string}
     */
    private function draftAttributes(MediaRequest $request): array
    {
        return [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'token' => $request->string('token')->toString(),
            'target_module' => (string) $request->route('module'),
            'target_entity' => (string) $request->route('entity'),
        ];
    }

    /**
     * @return Collection<int, Media>
     */
    private function pendingMedia(MediaDraft $draft): Collection
    {
        /** @var Collection<int, Media> $media */
        return $draft->media()->orderBy('order_column')->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<Media, Model>
     */
    private function mediaQuery(Model $record): mixed
    {
        /** @phpstan-ignore method.notFound */
        return $record->media()->orderBy('order_column');
    }

    private function errorResponse(Request $request, Throwable $exception, int $status): Response
    {
        return new ResponseBuilder($request)
            ->setData($exception)
            ->setStatus($status)
            ->json();
    }
}
