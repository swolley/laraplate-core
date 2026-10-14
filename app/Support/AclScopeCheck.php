<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Log;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\RelationFilter;
use Modules\Core\Casts\ScopeFilter;
use Modules\Core\Exceptions\ScopeFilterException;
use Modules\Core\Models\ACL;
use Throwable;

/**
 * The walk of the scope nodes of ACL filters, shared by the check at save ({@see \Modules\Core\Rules\AclScopeFilters})
 * and the check at use ({@see assertApplies()}): each scope node is visited with the model it reaches (the
 * permission's model, the related model inside a relation filter, each morph type of a morph relation), and each node
 * of a kind no walker knows is reported instead of skipped. A relation or a morph type that does not resolve is not
 * descended into: the relation rule refuses it at save and the query walkers refuse it at use.
 */
final class AclScopeCheck
{
    /**
     * Whether the filters hold a scope node, or a node of an unknown kind, at any depth: only then do they need the walk.
     */
    public static function holdsScopeFilter(FiltersGroup $filters): bool
    {
        foreach ($filters->filters as $node) {
            $holds = match (true) {
                $node instanceof FiltersGroup => self::holdsScopeFilter($node),
                $node instanceof RelationFilter => self::holdsScopeFilter($node->filters),
                $node instanceof Filter => false,
                default => true,
            };

            if ($holds) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calls `$on_scope` for every scope node with the model it reaches, and `$on_unknown` for every node that is
     * neither a group, a relation filter, a scope node nor a column filter.
     *
     * @param  Closure(ScopeFilter, Model|null): void  $on_scope
     * @param  Closure(mixed, Model|null): void  $on_unknown
     */
    public static function walk(FiltersGroup $filters, ?Model $model, Closure $on_scope, Closure $on_unknown): void
    {
        foreach ($filters->filters as $node) {
            if ($node instanceof FiltersGroup) {
                self::walk($node, $model, $on_scope, $on_unknown);
            } elseif ($node instanceof RelationFilter) {
                foreach (self::relatedModels($model, $node) as $related) {
                    self::walk($node->filters, $related, $on_scope, $on_unknown);
                }
            } elseif ($node instanceof ScopeFilter) {
                $on_scope($node, $model);
            } else {
                self::reportUnknown($node, $model, $on_unknown);
            }
        }
    }

    /**
     * Checks at use that every scope node of an ACL still applies: each is run, its placeholders resolved, on a
     * throwaway query of the model it reaches, which is built and never executed. A scope that is no longer declared,
     * takes the parameters badly, does more than add where conditions or fails when it runs denies the read, and so
     * does a node of an unknown kind; the ACL id is logged, never a parameter value.
     *
     * @param  Model|null  $model  The model the ACL's permission is about; null when it cannot be resolved
     *
     * @throws AuthorizationException when a scope node no longer applies
     */
    public static function assertApplies(ACL $acl, string $permission_name, FiltersGroup $filters, ?Model $model): void
    {
        self::walk(
            $filters,
            $model,
            static function (ScopeFilter $filter, ?Model $target) use ($acl, $permission_name): void {
                self::assertScopeApplies($acl, $permission_name, $filter, $target);
            },
            static function (mixed $node, ?Model $target) use ($acl, $permission_name): void {
                self::deny($acl, $permission_name, null, $target, 'the filters hold a node of an unknown kind');
            },
        );
    }

    /**
     * A copy of the scope node with each placeholder parameter (`@now`, `@today`, `@user.<attribute>`) replaced by
     * its live value.
     */
    public static function resolveParameters(ScopeFilter $filter): ScopeFilter
    {
        return new ScopeFilter($filter->scope, array_map(static fn (mixed $parameter): mixed => FilterPlaceholders::resolve($parameter), $filter->parameters));
    }

    /**
     * The models a relation filter reaches from the model: the related model, or each morph type of a morph
     * relation. None when the relation does not resolve.
     *
     * @return list<Model>
     */
    private static function relatedModels(?Model $model, RelationFilter $filter): array
    {
        $relation = $model instanceof Model ? RelationGuard::relationOf($model, $filter->relation) : null;

        if (! $relation instanceof Relation) {
            return [];
        }

        if (! $relation instanceof MorphTo) {
            return [$relation->getRelated()];
        }

        $models = [];

        foreach ($filter->morph_types ?? [] as $type) {
            $morph_model = RelationGuard::morphModel($type);

            if ($morph_model instanceof Model) {
                $models[] = $morph_model->newInstance();
            }
        }

        return $models;
    }

    /**
     * A column filter needs no check; any other node reaching here is of a kind no walker knows.
     *
     * @param  Closure(mixed, Model|null): void  $on_unknown
     */
    private static function reportUnknown(mixed $node, ?Model $model, Closure $on_unknown): void
    {
        if (! $node instanceof Filter) {
            $on_unknown($node, $model);
        }
    }

    /**
     * @throws AuthorizationException when the scope node no longer applies
     */
    private static function assertScopeApplies(ACL $acl, string $permission_name, ScopeFilter $filter, ?Model $model): void
    {
        if (! $model instanceof Model) {
            self::deny($acl, $permission_name, $filter->scope, null, 'the entity of the permission cannot be resolved');
        }

        try {
            ScopeGuard::apply($model->newModelQuery(), self::resolveParameters($filter));
        } catch (ScopeFilterException $exception) {
            // The guard's messages name the scope and the model, never a parameter value.
            self::deny($acl, $permission_name, $filter->scope, $model, $exception->getMessage());
        } catch (Throwable $exception) {
            // The scope's own message may quote a value: only its class is logged.
            self::deny($acl, $permission_name, $filter->scope, $model, sprintf('the scope fails when it runs (%s)', $exception::class));
        }
    }

    /**
     * @throws AuthorizationException always
     */
    private static function deny(ACL $acl, string $permission_name, ?string $scope, ?Model $model, string $reason): never
    {
        Log::warning('An ACL scope filter no longer applies, the read is denied.', [
            'acl_id' => $acl->getKey(),
            'permission' => $permission_name,
            'scope' => $scope,
            'model' => $model instanceof Model ? $model::class : null,
            'reason' => $reason,
        ]);

        throw new AuthorizationException('User not allowed to access this resource');
    }
}
