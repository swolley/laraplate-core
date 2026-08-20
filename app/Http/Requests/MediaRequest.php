<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasMedia;
use Override;
use RuntimeException;
use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Shared base for the generic media endpoints.
 *
 * Reuses {@see CrudRequest} to resolve the owner `{module}/{entity}` into a
 * concrete Eloquent template model (table + connection), then guards that the
 * model is media-enabled. The owner entity's CRUD permission is enforced in the
 * controller so an {@see \Illuminate\Auth\Access\AuthorizationException} maps to
 * a 401 envelope, exactly like the sibling CRUD endpoints.
 */
abstract class MediaRequest extends CrudRequest
{
    /**
     * The CRUD operation whose permission gates this media action
     * (`select` for reads, `update` for writes).
     */
    abstract public function mediaOperation(): string;

    /**
     * The resolved owner template model, carrying the table and connection used
     * for the permission check.
     */
    public function mediaModel(): Model
    {
        throw_unless($this->model instanceof Model, new RuntimeException('Owner entity has not been resolved.'));

        return $this->model;
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        throw_unless(
            $this->modelSupportsMedia($this->mediaModel()),
            new NotFoundHttpException('Media is not supported for this entity.'),
        );
    }

    private function modelSupportsMedia(Model $model): bool
    {
        return $model instanceof SpatieHasMedia
            && in_array(HasMedia::class, class_uses_recursive($model), true);
    }
}
