<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Staudenmeir\LaravelAdjacencyList\Eloquent\HasRecursiveRelationships;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Ancestors;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Bloodline;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Descendants;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\RootAncestor;
use Staudenmeir\LaravelAdjacencyList\Eloquent\Relations\Siblings;

/**
 * The adjacency-list relations with a declared return type. The package declares them only in PHPDoc, and a
 * relation a request names is called only when its declaration says it returns a relation
 * ({@see \Modules\Core\Support\RelationGuard}): without the type, `relations=parent` on a category was refused.
 *
 * @phpstan-require-extends \Illuminate\Database\Eloquent\Model
 */
trait HasTypedRecursiveRelationships
{
    use HasRecursiveRelationships {
        ancestors as private untypedAncestors;
        ancestorsAndSelf as private untypedAncestorsAndSelf;
        bloodline as private untypedBloodline;
        children as private untypedChildren;
        childrenAndSelf as private untypedChildrenAndSelf;
        descendants as private untypedDescendants;
        descendantsAndSelf as private untypedDescendantsAndSelf;
        parent as private untypedParent;
        parentAndSelf as private untypedParentAndSelf;
        rootAncestor as private untypedRootAncestor;
        siblings as private untypedSiblings;
        siblingsAndSelf as private untypedSiblingsAndSelf;
    }

    /**
     * @return Ancestors<static, static>
     */
    public function ancestors(): Ancestors
    {
        return $this->untypedAncestors();
    }

    /**
     * @return Ancestors<static, static>
     */
    public function ancestorsAndSelf(): Ancestors
    {
        return $this->untypedAncestorsAndSelf();
    }

    /**
     * @return Bloodline<static, static>
     */
    public function bloodline(): Bloodline
    {
        return $this->untypedBloodline();
    }

    /**
     * @return HasMany<static, $this>
     */
    public function children(): HasMany
    {
        return $this->untypedChildren();
    }

    /**
     * @return Descendants<static, static>
     */
    public function childrenAndSelf(): Descendants
    {
        return $this->untypedChildrenAndSelf();
    }

    /**
     * @return Descendants<static, static>
     */
    public function descendants(): Descendants
    {
        return $this->untypedDescendants();
    }

    /**
     * @return Descendants<static, static>
     */
    public function descendantsAndSelf(): Descendants
    {
        return $this->untypedDescendantsAndSelf();
    }

    /**
     * @return BelongsTo<static, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->untypedParent();
    }

    /**
     * @return Ancestors<static, static>
     */
    public function parentAndSelf(): Ancestors
    {
        return $this->untypedParentAndSelf();
    }

    /**
     * @return RootAncestor<static, static>
     */
    public function rootAncestor(): RootAncestor
    {
        return $this->untypedRootAncestor();
    }

    /**
     * @return Siblings<static, static>
     */
    public function siblings(): Siblings
    {
        return $this->untypedSiblings();
    }

    /**
     * @return Siblings<static, static>
     */
    public function siblingsAndSelf(): Siblings
    {
        return $this->untypedSiblingsAndSelf();
    }
}
