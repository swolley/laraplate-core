<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Modules\Core\Casts\IParsableRequest;
use Override;

/**
 * Resolves {module}/{entity} like every CRUD request and isolates the action
 * payload.
 *
 * Model validation rules are deliberately not applied: a domain action invokes
 * an operation, it does not write the record's own fields, so requiring them
 * would reject legitimate calls.
 */
final class DomainActionRequest extends CrudRequest implements IParsableRequest
{
    /**
     * Keys that address the request itself rather than the action.
     *
     * @var list<string>
     */
    private const array RESERVED = ['id', 'module', 'entity', 'action', 'connection'];

    #[Override]
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'id' => ['required'],
        ]);
    }

    public function action(): string
    {
        return (string) $this->route('action');
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $all */
        $all = $this->all();

        return array_diff_key($all, array_flip(self::RESERVED));
    }

    #[Override]
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $id = $this->route('id') ?? $this->input('id');

        if ($id !== null && $id !== '') {
            $this->merge(['id' => $id]);
        }
    }
}
