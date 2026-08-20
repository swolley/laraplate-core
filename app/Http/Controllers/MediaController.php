<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Http\Requests\MediaDeleteRequest;
use Modules\Core\Http\Requests\MediaListRequest;
use Modules\Core\Http\Requests\MediaRequest;
use Modules\Core\Http\Requests\MediaUploadRequest;
use Modules\Core\Http\Resources\MediaResource;
use Modules\Core\Models\Media;
use Modules\Core\Services\Authorization\AuthorizationService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Generic media HTTP API for any media-enabled owner entity.
 *
 * Endpoints are addressed as `{module}/{entity}/{id}` and reuse the owner
 * entity's CRUD ACL permissions: LIST requires `select`, UPLOAD and DELETE
 * require `update`. No media-specific permissions are introduced.
 */
final class MediaController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * List the owner record's media grouped by collection name.
     */
    public function list(MediaListRequest $request): Response
    {
        return $this->handle($request, function () use ($request): Response {
            $record = $request->mediaRecord();

            $grouped = $this->mediaQuery($record)
                ->get()
                ->groupBy('collection_name')
                ->map(fn (Collection $items): array => MediaResource::collection($items->values())->resolve($request))
                ->toArray();

            return new ResponseBuilder($request)->setData($grouped)->json();
        });
    }

    /**
     * Add an uploaded file to one of the owner model's registered collections.
     */
    public function upload(MediaUploadRequest $request): Response
    {
        return $this->handle($request, function () use ($request): Response {
            $record = $request->mediaRecord();

            /** @var UploadedFile $file */
            $file = $request->file('file');

            $name = $request->string('name')->toString();
            $custom_properties = $request->input('custom_properties');

            $adder = $record->addMedia($file->getRealPath())
                ->usingFileName($name !== '' ? $name : $file->getClientOriginalName());

            if ($name !== '') {
                $adder->usingName($name);
            }

            if (is_array($custom_properties) && $custom_properties !== []) {
                $adder->withCustomProperties($custom_properties);
            }

            $media = $adder->toMediaCollection($request->string('collection')->toString());

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
        return $this->handle($request, function () use ($request): Response {
            $record = $request->mediaRecord();
            $identifier = (string) $request->route('media');

            $media = $this->mediaQuery($record)
                ->where(static function ($query) use ($identifier): void {
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
     * Enforce the owner entity's CRUD permission, then run the endpoint body,
     * mapping authorization and not-found failures onto the shared envelope.
     */
    private function handle(MediaRequest $request, Closure $callback): Response
    {
        try {
            $model = $request->mediaModel();

            $this->authorizationService->ensurePermission(
                $request,
                $model->getTable(),
                $request->mediaOperation(),
                $model->getConnectionName(),
            );

            return $callback();
        } catch (AuthorizationException $exception) {
            return $this->errorResponse($request, $exception, Response::HTTP_UNAUTHORIZED);
        } catch (ModelNotFoundException|NotFoundHttpException $exception) {
            return $this->errorResponse($request, $exception, Response::HTTP_NOT_FOUND);
        }
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
