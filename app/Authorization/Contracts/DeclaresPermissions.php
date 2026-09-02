<?php

declare(strict_types=1);

namespace Modules\Core\Authorization\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * A module's permission declaration, read by {@see \Modules\Core\Authorization\PermissionManifest}.
 *
 * `permission:refresh` generates the generic CRUD verbs by inspecting the models
 * themselves; everything a module distinguishes beyond them (posting an invoice,
 * releasing a production order, overriding a workflow) is declared here, because
 * Core has no way to infer it.
 *
 * The implementation is found by convention at
 * `Modules\{Module}\Authorization\{Module}Permissions` and is never instantiated:
 * these are static declarations, resolved only when the console asks for them.
 */
interface DeclaresPermissions
{
    /**
     * Domain operations per model, beyond the generic CRUD verbs.
     *
     * @return array<class-string<Model>, list<string>>
     */
    public static function operations(): array;

    /**
     * Models that must not get CRUD permissions at all.
     *
     * Infrastructure a user never addresses directly: rows written as a side
     * effect of an action already authorized on the entity that caused it.
     * Granting them permissions would mean granting every user write access to
     * the bookkeeping of their own actions.
     *
     * @return list<class-string<Model>>
     */
    public static function excludedModels(): array;
}
