<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Stubs\Filament;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Contracts\IResourceSchemaContributor;
use Modules\Core\Tests\Stubs\Search\StubSearchableModel;

/**
 * Test contributor that adds one infolist section and one record action to
 * {@see StubSearchableModel}. Uses plain string markers so the registry can be
 * exercised without constructing real Filament components.
 */
final class StubSchemaContributor implements IResourceSchemaContributor
{
    /**
     * @param  class-string<Model>  $target
     */
    public function __construct(private string $target = StubSearchableModel::class) {}

    public function contributesTo(): string
    {
        return $this->target;
    }

    public function infolistSections(Model $record): array
    {
        return ['analysis-section'];
    }

    public function recordActions(Model $record): array
    {
        return ['re-analyze-action'];
    }
}
