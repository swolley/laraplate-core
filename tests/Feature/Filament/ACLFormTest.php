<?php

declare(strict_types=1);

use Filament\Schemas\Schema;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Filament\Resources\ACLS\Schemas\ACLForm;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Tests\Stubs\Filament\SchemaHarnessComponent;

function acl_with_nested_filters(): ACL
{
    $permission = Permission::factory()->create(['name' => 'default.acl_form_' . uniqid() . '.select']);

    $acl = new ACL;
    $acl->fill([
        'permission_id' => $permission->id,
        'filters' => new FiltersGroup([
            new Filter('status', 'published', FilterOperator::Equals),
            new FiltersGroup([
                new Filter('country', ['IT', 'DE'], FilterOperator::In),
            ], WhereClause::Or),
        ]),
        'sort' => [['property' => 'created_at', 'direction' => 'desc']],
        'unrestricted' => false,
        'priority' => 10,
        'is_active' => true,
    ]);
    $acl->save();

    return $acl->refresh();
}

it('saves an acl with nested filters through model validation', function (): void {
    $acl = acl_with_nested_filters();

    expect($acl->exists)->toBeTrue()
        ->and($acl->filters)->toBeInstanceOf(FiltersGroup::class)
        ->and($acl->filters->filters)->toHaveCount(2);
});

it('exposes every editable acl attribute in the form', function (): void {
    $schema = ACLForm::configure(new Schema());

    $names = array_map(
        static fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null,
        $schema->getComponents(),
    );

    expect($names)->toContain('permission_id', 'role_id', 'unrestricted', 'filters', 'sort', 'description', 'priority', 'is_active');
});

it('hydrates the filters editor with pretty printed json and dehydrates it back', function (): void {
    $acl = acl_with_nested_filters();

    $schema = Schema::make(new SchemaHarnessComponent)->model(ACL::class)->statePath('data');
    ACLForm::configure($schema);
    $schema->record($acl);
    $schema->fill($acl->attributesToArray());

    $editor_state = $schema->getComponent(fn (mixed $component): bool => method_exists($component, 'getName') && $component->getName() === 'filters')->getState();

    expect($editor_state)->toBeString()
        ->and(json_decode((string) $editor_state, true))->toBe($acl->filters->toArray());

    $state = $schema->getState(shouldCallHooksBefore: false);

    expect($state['filters'])->toBe($acl->filters->toArray());
});

it('keeps the filters round trip lossless when the form state is saved back', function (): void {
    $acl = acl_with_nested_filters();
    $original = $acl->filters->toArray();

    $acl->fill(['filters' => ACLForm::decodeFilters(ACLForm::encodeFilters($acl->filters))]);
    $acl->save();

    expect($acl->refresh()->filters->toArray())->toBe($original);
});

it('decodes an empty filters editor to null', function (): void {
    expect(ACLForm::decodeFilters(''))->toBeNull()
        ->and(ACLForm::decodeFilters(null))->toBeNull()
        ->and(ACLForm::encodeFilters(null))->toBeNull();
});
