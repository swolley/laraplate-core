<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use Modules\Core\Casts\Filter;
use Modules\Core\Casts\FilterOperator;
use Modules\Core\Casts\FiltersGroup;
use Modules\Core\Casts\WhereClause;
use Modules\Core\Filament\Resources\ACLS\Pages\CreateACL;
use Modules\Core\Filament\Resources\ACLS\Schemas\ACLForm;
use Modules\Core\Models\ACL;
use Modules\Core\Models\Permission;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Support\PermissionName;
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

/**
 * The create page of the ACL resource as a superadmin, on a permission about users.
 *
 * @param  class-string<Illuminate\Database\Eloquent\Model>  $model_class
 * @return array{0: Livewire\Features\SupportTesting\Testable, 1: Permission}
 */
function acl_create_page(string $model_class = User::class): array
{
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate(config('permission.roles.superadmin'), 'web'));

    test()->actingAs($actor);
    Filament::setCurrentPanel('admin');

    $permission = Permission::query()->firstOrCreate([
        'name' => PermissionName::forClass($model_class, 'select'),
        'guard_name' => 'api',
    ]);

    return [Livewire::test(CreateACL::class), $permission];
}

it('mentions the scope node in the helper text of the filters editor', function (): void {
    [$page] = acl_create_page();

    $page->assertSee('{"scope":"valid"}');
});

it('saves an acl holding a declared scope node through the create page', function (): void {
    [$page, $permission] = acl_create_page();

    $page->fillForm([
        'permission_id' => $permission->id,
        'filters' => json_encode(['operator' => 'and', 'filters' => [['scope' => 'valid'], ['scope' => 'validAt', 'parameters' => ['@today']]]]),
        'priority' => 1,
    ])->call('create')->assertHasNoFormErrors();

    $acl = ACL::query()->where('permission_id', $permission->id)->firstOrFail();

    expect($acl->filters->toArray()['filters'])->toBe([['scope' => 'valid', 'parameters' => []], ['scope' => 'validAt', 'parameters' => ['@today']]]);
});

it('refuses an acl holding an undeclared scope node through the create page', function (): void {
    [$page, $permission] = acl_create_page();

    $page->fillForm([
        'permission_id' => $permission->id,
        'filters' => json_encode(['operator' => 'and', 'filters' => [['scope' => 'doesNotExist']]]),
        'priority' => 1,
    ])->call('create');

    expect(ACL::query()->where('permission_id', $permission->id)->exists())->toBeFalse();
    $page->assertHasFormErrors(['filters']);
    expect(collect($page->errors()->get('data.filters'))->implode(' '))->toContain('doesNotExist');
});
