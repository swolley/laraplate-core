<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Core\Casts\FieldType;
use Modules\Core\Console\CreateEntityCommand;
use Modules\Core\Models\Entity;
use Modules\Core\Models\Field;
use Modules\Core\Models\Preset;
use Modules\Core\Tests\Stubs\Casts\EntityTypeStub;

beforeEach(function (): void {
    if (! Schema::hasTable('fields')) {
        $this->markTestSkipped('CreateEntityCommand requires fields table.');
    }

    static $command_registered = false;

    if (! $command_registered) {
        app(ConsoleKernel::class)->registerCommand(app(CreateEntityCommand::class));
        $command_registered = true;
    }
});

/**
 * @return list<string>
 */
function entityTypeChoiceOptions(): array
{
    return EntityTypeStub::values();
}

it('shows help without prompting', function (): void {
    $this->artisan('model:create-entity', ['--help' => true])
        ->assertExitCode(0)
        ->expectsOutputToContain('model:create-entity');
});

it('defines optional entity argument and content-model option', function (): void {
    $command = app(CreateEntityCommand::class);

    $arguments_method = new ReflectionMethod($command, 'getArguments');
    $arguments_method->setAccessible(true);
    $options_method = new ReflectionMethod($command, 'getOptions');
    $options_method->setAccessible(true);

    $arguments = $arguments_method->invoke($command);
    $options = $options_method->invoke($command);

    expect($arguments)->toHaveCount(1)
        ->and($arguments[0][0])->toBe('entity')
        ->and($options)->toHaveCount(1)
        ->and($options[0][0])->toBe('content-model');
});

it('creates entity preset and field pivot when entity name is passed as argument', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_field_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    $entity_name = 'CmdEntity' . uniqid();
    $expected_slug = Str::slug($entity_name);

    $this->artisan('model:create-entity', ['entity' => $entity_name])
        ->expectsQuestion('Slug', $expected_slug)
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), 'null')
        ->assertExitCode(0);

    $entity = Entity::query()->where('name', $entity_name)->first();
    expect($entity)->not->toBeNull();

    $preset = Preset::query()->where('entity_id', $entity->id)->where('name', 'standard')->first();
    expect($preset)->not->toBeNull()
        ->and($preset->fields()->count())->toBe(1);
});

it('prompts for name when the entity argument is omitted', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_field2_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    $typed_name = 'AcmeOrg' . uniqid();

    $this->artisan('model:create-entity')
        ->expectsQuestion('Name', $typed_name)
        ->expectsQuestion('Slug', Str::slug($typed_name))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), 'null')
        ->assertExitCode(0);

    expect(Entity::query()->where('name', $typed_name)->exists())->toBeTrue();
});

it('parses switch default true when the field is required', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_switch_' . uniqid(),
        'type' => FieldType::SWITCH,
        'options' => new stdClass(),
    ]);

    $entity_name = 'EntSwitch' . uniqid();

    $this->artisan('model:create-entity', ['entity' => $entity_name])
        ->expectsQuestion('Slug', Str::slug($entity_name))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), true)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), 'true')
        ->assertExitCode(0);
});

it('parses switch default false when the field is not required', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_switch2_' . uniqid(),
        'type' => FieldType::SWITCH,
        'options' => new stdClass(),
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntSw2' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntSw2' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), 'false')
        ->assertExitCode(0);
});

it('parses multiselect field default as empty json array', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_msel_' . uniqid(),
        'type' => FieldType::SELECT,
        'options' => (object) ['multiple' => true],
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntMsel' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntMsel' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), '[]')
        ->assertExitCode(0);
});

it('parses checkbox field default as empty json array', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_cb_' . uniqid(),
        'type' => FieldType::CHECKBOX,
        'options' => new stdClass(),
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntCb' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntCb' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), '[]')
        ->assertExitCode(0);
});

it('parses integer default from numeric text', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_num_' . uniqid(),
        'type' => FieldType::NUMBER,
        'options' => new stdClass(),
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntNum' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntNum' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), '42')
        ->assertExitCode(0);
});

it('parses float default when the value contains a decimal point', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_float_' . uniqid(),
        'type' => FieldType::NUMBER,
        'options' => new stdClass(),
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntFloat' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntFloat' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), '3.14')
        ->assertExitCode(0);
});

it('parses json array default for bracketed input', function (): void {
    $field = Field::query()->create([
        'name' => 'cmd_json_' . uniqid(),
        'type' => FieldType::TEXT,
        'options' => new stdClass(),
    ]);

    $suffix = uniqid();

    $this->artisan('model:create-entity', ['entity' => 'EntJson' . $suffix])
        ->expectsQuestion('Slug', Str::slug('EntJson' . $suffix))
        ->expectsChoice('Choose the type of the entity', 'contents', entityTypeChoiceOptions())
        ->expectsChoice('Choose fields for the preset', [(string) $field->id], [$field->id => $field->name])
        ->expectsQuestion(sprintf("Do you want '%s' to be required?", $field->name), false)
        ->expectsQuestion(sprintf("Specify a default value for '%s'", $field->name), '[1]')
        ->assertExitCode(0);
});
