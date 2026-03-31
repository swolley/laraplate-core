<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SuggestPrompt;
use Laravel\Prompts\TextPrompt;
use Modules\Core\Console\ModelMakeCommand;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

if (! class_exists('ModelMakeCoverageFieldsStub')) {
    class ModelMakeCoverageFieldsStub extends Model
    {
        protected $fillable = ['name'];

        protected $hidden = ['secret'];

        protected $attributes = ['status' => 'draft'];

        protected $appends = ['full_name'];

        protected function casts(): array
        {
            return ['meta' => 'array'];
        }
    }
}

if (! class_exists('ModelMakeMigrationSpyCommand')) {
    class ModelMakeMigrationSpyCommand extends SymfonyCommand
    {
        public static array $lastArguments = [];

        protected function configure(): void
        {
            $this->setName('make:migration');
            $this->addArgument('name');
            $this->addOption('create');
            $this->addOption('update');
            $this->addOption('fullpath');
        }

        protected function execute(Symfony\Component\Console\Input\InputInterface $input, Symfony\Component\Console\Output\OutputInterface $output): int
        {
            self::$lastArguments = [
                'name' => (string) $input->getArgument('name'),
                'create' => $input->getOption('create'),
                'update' => $input->getOption('update'),
                'fullpath' => (bool) $input->getOption('fullpath'),
            ];

            return 0;
        }
    }
}

uses(LaravelTestCase::class, RefreshDatabase::class);

afterEach(function (): void {
    Prompt::interactive(true);

    $fallbackProp = new ReflectionProperty(Prompt::class, 'shouldFallback');
    $fallbackProp->setValue(null, false);

    $fallbacksProp = new ReflectionProperty(Prompt::class, 'fallbacks');
    $fallbacksProp->setValue(null, []);

    foreach ([TextPrompt::class, SuggestPrompt::class, ConfirmPrompt::class] as $promptClass) {
        $shouldFallback = new ReflectionProperty($promptClass, 'shouldFallback');
        $shouldFallback->setValue(null, false);

        $fallbacks = new ReflectionProperty($promptClass, 'fallbacks');
        $fallbacks->setValue(null, []);
    }
});

function modelMakeCommandWithOutput(): ModelMakeCommand
{
    $command = app(ModelMakeCommand::class);
    $command->setLaravel(app());
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $reflection = new ReflectionProperty(Command::class, 'output');
    $reflection->setValue($command, $output);

    return $command;
}

it('exposes expected command metadata', function (): void {
    $reflection = new ReflectionClass(ModelMakeCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Create or modify an Eloquent model class')
        ->and($reflection->hasMethod('handle'))->toBeTrue();
});

it('detects reserved names through generator logic', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'isReservedName');
    $method->setAccessible(true);

    expect($method->invoke($command, 'Class'))->toBeTrue()
        ->and($method->invoke($command, 'MyCoverageEntity'))->toBeFalse();
});

it('returns available models array via possibleModels', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'possibleModels');
    $method->setAccessible(true);
    $result = $method->invoke($command);

    expect($result)->toBeArray();
});

it('qualifies classes correctly for app and modules namespaces', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'qualifyClass');
    $method->setAccessible(true);

    $appResult = $method->invoke($command, 'DemoItem');
    $moduleResult = $method->invoke($command, 'Modules\\Core\\Models\\DemoItem');

    expect($appResult)->toBeString()
        ->and($appResult)->toContain('Models')
        ->and($moduleResult)->toBe('Modules\\Core\\Models\\DemoItem');
});

it('builds model path for app namespace', function (): void {
    $command = modelMakeCommandWithOutput();
    $qualify = new ReflectionMethod(ModelMakeCommand::class, 'qualifyClass');
    $pathMethod = new ReflectionMethod(ModelMakeCommand::class, 'getPath');
    $qualify->setAccessible(true);
    $pathMethod->setAccessible(true);

    $qualified = $qualify->invoke($command, 'PathCoverageModel');
    $path = $pathMethod->invoke($command, $qualified);

    expect($path)->toContain('PathCoverageModel.php')
        ->and($path)->toContain('/app/');
});

it('builds model path for modules namespace branch', function (): void {
    $command = modelMakeCommandWithOutput();
    $pathMethod = new ReflectionMethod(ModelMakeCommand::class, 'getPath');
    $pathMethod->setAccessible(true);

    $path = $pathMethod->invoke($command, 'Modules\\Core\\Models\\ModulePathCoverageModel');

    expect($path)->toContain('Core')
        ->and($path)->toContain('/app/Models/')
        ->and($path)->toContain('ModulePathCoverageModel.php');
});

it('handles array_last helper branches', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'array_last');
    $method->setAccessible(true);

    $found = $method->invoke($command, 'x', ['a', 'x', 'b', 'x']);
    $notFound = $method->invoke($command, 'z', ['a', 'x', 'b', 'x']);

    expect($found)->toBe(3)
        ->and($notFound)->toBeFalse();
});

it('adds default sections and strips app models namespace', function (): void {
    $command = modelMakeCommandWithOutput();
    $addDefaultSections = new ReflectionMethod(ModelMakeCommand::class, 'addDefaultSections');
    $stripModelsNamespace = new ReflectionMethod(ModelMakeCommand::class, 'stripModelsNamespace');
    $addDefaultSections->setAccessible(true);
    $stripModelsNamespace->setAccessible(true);

    $input = <<<'PHP'
<?php
namespace App\Models;
class SampleModel extends Model
{
}
PHP;

    $output = $addDefaultSections->invoke($command, 'App\\Models\\SampleModel', $input);
    $stripped = $stripModelsNamespace->invoke($command, 'App\\Models\\SampleModel');

    expect($output)->toContain("public \$table = 'sample_models';")
        ->and($output)->toContain('#region [RELATIONS]')
        ->and($stripped)->toBe('SampleModel');
});

it('recognizes default field types for common naming conventions', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'recognizeDefaultFieldType');
    $method->setAccessible(true);

    expect($method->invoke($command, 'published_at'))->toBe('datetime')
        ->and($method->invoke($command, 'owner_id'))->toBe('integer')
        ->and($method->invoke($command, 'is_active'))->toBe('boolean')
        ->and($method->invoke($command, 'uuid'))->toBeIn(['uuid', 'guid'])
        ->and($method->invoke($command, 'title'))->toBe('string');
});

it('resolves cast mapping and fallback branch', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'getCodeTypeFromCast');
    $method->setAccessible(true);

    expect($method->invoke($command, 'string'))->toBe('string')
        ->and($method->invoke($command, 'json'))->toBe('array')
        ->and($method->invoke($command, 'datetime'))->toBe('datetime');
});

it('injects code snippets at end of class and in fillable/hidden/casts/validations', function (): void {
    $command = modelMakeCommandWithOutput();
    $inject = new ReflectionMethod(ModelMakeCommand::class, 'injectCodeAtTheEnd');
    $fillable = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoFillable');
    $hidden = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoHidden');
    $casts = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoCasts');
    $validations = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoValidations');
    $inject->setAccessible(true);
    $fillable->setAccessible(true);
    $hidden->setAccessible(true);
    $casts->setAccessible(true);
    $validations->setAccessible(true);

    $classCode = <<<'PHP'
<?php
class DemoModel {
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    public $rules = [];
}
PHP;

    $classCode = $inject->invoke($command, $classCode, "\n//snippet");
    $classCode = $fillable->invoke($command, $classCode, 'name');
    $classCode = $hidden->invoke($command, $classCode, 'secret');
    $classCode = $casts->invoke($command, $classCode, 'meta', 'array');
    $classCode = $validations->invoke($command, $classCode, 'name', 'string', false);

    expect($classCode)->toContain("'name'")
        ->and($classCode)->toContain("'secret'")
        ->and($classCode)->toContain("'meta' => 'array'")
        ->and($classCode)->toContain("'always' => [")
        ->and($classCode)->toContain('//snippet');
});

it('injects casts and validations when sections are missing', function (): void {
    $command = modelMakeCommandWithOutput();
    $casts = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoCasts');
    $validations = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoValidations');
    $casts->setAccessible(true);
    $validations->setAccessible(true);

    $classCode = <<<'PHP'
<?php
class NoSections {
}
PHP;

    $classCode = $casts->invoke($command, $classCode, 'meta', 'array');
    $classCode = $validations->invoke($command, $classCode, 'title', 'string', true);

    expect($classCode)->toContain('protected $casts = [')
        ->and($classCode)->toContain("'meta' => 'array'")
        ->and($classCode)->toContain('public $rules = [')
        ->and($classCode)->toContain("'title' => 'string'");
});

it('adds accessors mutators and property annotation', function (): void {
    $command = modelMakeCommandWithOutput();
    $addAccessorMutator = new ReflectionMethod(ModelMakeCommand::class, 'addPropertyIntoAccessorsMutators');
    $addAnnotation = new ReflectionMethod(ModelMakeCommand::class, 'addNewPropertyAnnotation');
    $addAccessorMutator->setAccessible(true);
    $addAnnotation->setAccessible(true);

    $allTypes = (new ReflectionProperty(ModelMakeCommand::class, 'availableTypes'));
    $allTypes->setAccessible(true);
    $flattenTypes = array_merge(...array_values($allTypes->getValue($command)));

    $classCode = <<<'PHP'
<?php
namespace App\Models;

class DemoAnnotated extends Model
{
    #region [ACCESSORS_MUTATORS]
    #endregion
}
PHP;

    $classCode = $addAccessorMutator->invoke($command, $classCode, 'published_at', 'datetime', false, $flattenTypes);
    $classCode = $addAnnotation->invoke($command, 'App\\Models\\DemoAnnotated', $classCode, 'published_at', 'datetime', false);

    expect($classCode)->toContain('setPublishedAtAttribute')
        ->and($classCode)->toContain('@property datetime $published_at');
});

it('injects imports idempotently', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'injectImportClass');
    $method->setAccessible(true);

    $classCode = <<<'PHP'
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ImportDemo extends Model {}
PHP;

    $first = $method->invokeArgs($command, [&$classCode, Illuminate\Support\Carbon::class]);
    $second = $method->invokeArgs($command, [&$classCode, Illuminate\Support\Carbon::class]);

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and(mb_substr_count($classCode, 'use Illuminate\Support\Carbon;'))->toBe(1);
});

it('handles relation helpers and updates relation code', function (): void {
    $command = modelMakeCommandWithOutput();
    $getReversed = new ReflectionMethod(ModelMakeCommand::class, 'getReversedRelationType');
    $proposeInverse = new ReflectionMethod(ModelMakeCommand::class, 'proposeInverseName');
    $updateRelation = new ReflectionMethod(ModelMakeCommand::class, 'updateClassWithNewRelation');
    $getReversed->setAccessible(true);
    $proposeInverse->setAccessible(true);
    $updateRelation->setAccessible(true);

    expect($getReversed->invoke($command, 'ManyToOne'))->toBe('OneToMany')
        ->and($getReversed->invoke($command, 'unknown'))->toBeNull()
        ->and($proposeInverse->invoke($command, 'Post', 'ManyToMany'))->toBe('post')
        ->and($proposeInverse->invoke($command, 'Post', 'unknown'))->toBeNull();

    $classCode = <<<'PHP'
<?php
namespace App\Models;

class RelDemo extends Model
{
    #region [RELATIONS]
    #endregion
}
PHP;
    $tmpPath = sys_get_temp_dir() . '/rel_demo_' . uniqid('', false) . '.php';
    file_put_contents($tmpPath, $classCode);

    $updated = $updateRelation->invoke(
        $command,
        'App\\Models\\RelDemo',
        $classCode,
        $tmpPath,
        'items',
        'relation\\OneToMany',
        'User',
        true,
    );

    expect($updated)->toContain('function items():')
        ->and($updated)->toContain('//relation\\OneToMany');

    @unlink($tmpPath);
});

it('extracts existing class fields from fillable casts hidden and appends', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'getClassFields');
    $method->setAccessible(true);
    $fields = $method->invoke($command, ModelMakeCoverageFieldsStub::class);

    expect($fields)->toBeArray()
        ->and($fields)->toContain('name')
        ->and($fields)->toContain('meta')
        ->and($fields)->toContain('secret')
        ->and($fields)->toContain('status')
        ->and($fields)->toContain('full_name');
});

it('prints relation choice helper and returns selected relation type', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'printRelationsChoiceHelper');
    $method->setAccessible(true);

    SuggestPrompt::fallbackWhen(true);
    SuggestPrompt::fallbackUsing(static fn (): string => 'ManyToOne');

    $choice = $method->invoke(
        $command,
        'App\\Models\\Post',
        'author',
        'App\\Models\\User',
    );

    expect($choice)->toBe('ManyToOne');
});

it('handles relation details choice branch and normalizes related class', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'handleRelationDetailsChoice');
    $availableClasses = new ReflectionProperty(ModelMakeCommand::class, 'availableClasses');
    $method->setAccessible(true);
    $availableClasses->setAccessible(true);
    $availableClasses->setValue($command, ['App\\Models\\User']);

    SuggestPrompt::fallbackWhen(true);
    SuggestPrompt::fallbackUsing(static function (): string {
        static $answers = ['App\\Models\\User', 'OneToMany'];
        static $index = 0;

        return $answers[$index++] ?? 'App\\Models\\User';
    });

    $fieldType = 'relation';
    $related = $method->invokeArgs($command, ['App\\Models\\Post', 'author', &$fieldType]);

    expect($fieldType)->toBe('OneToMany')
        ->and($related)->toBe('App\\Models\\User');
});

it('proceeds with model attributes and updates class code', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'proceedWithModelAttributes');
    $method->setAccessible(true);

    $classCode = <<<'PHP'
<?php
namespace App\Models;

class TempCoverageModel extends Model
{
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    public $rules = [];
    #region [ACCESSORS_MUTATORS]
    #endregion
}
PHP;
    $tmpPath = sys_get_temp_dir() . '/model_make_attr_' . uniqid('', false) . '.php';
    file_put_contents($tmpPath, $classCode);

    TextPrompt::fallbackWhen(true);
    TextPrompt::fallbackUsing(static function (): string {
        static $answers = ['priority', ''];
        static $index = 0;

        return $answers[$index++] ?? '';
    });

    SuggestPrompt::fallbackWhen(true);
    SuggestPrompt::fallbackUsing(static fn (): string => 'integer');
    ConfirmPrompt::fallbackWhen(true);
    ConfirmPrompt::fallbackUsing(static fn (): bool => false);

    $method->invoke($command, ModelMakeCoverageFieldsStub::class, $classCode, $tmpPath);

    $updated = file_get_contents($tmpPath);
    expect($updated)->toContain("'priority'")
        ->and($updated)->toContain('setPriorityAttribute');

    @unlink($tmpPath);
});

it('updates class with new property including fillable casts validations and annotation', function (): void {
    $command = modelMakeCommandWithOutput();
    $method = new ReflectionMethod(ModelMakeCommand::class, 'updateClassWithNewProperty');
    $typesProperty = new ReflectionProperty(ModelMakeCommand::class, 'availableTypes');
    $method->setAccessible(true);
    $typesProperty->setAccessible(true);

    $allTypes = array_merge(...array_values($typesProperty->getValue($command)));
    $classCode = <<<'PHP'
<?php
namespace App\Models;

class TempRichModel extends Model
{
    protected $fillable = [];
    protected $hidden = [];
    protected $casts = [];
    public $rules = [];
    #region [ACCESSORS_MUTATORS]
    #endregion
}
PHP;
    $tmpPath = sys_get_temp_dir() . '/model_make_update_' . uniqid('', false) . '.php';
    file_put_contents($tmpPath, $classCode);

    $updated = $method->invoke(
        $command,
        'App\\Models\\TempRichModel',
        $classCode,
        $tmpPath,
        'published_at',
        'datetime',
        false,
        $allTypes,
    );

    expect($updated)->toContain("'published_at'")
        ->and($updated)->toContain("'published_at' => 'datetime'")
        ->and($updated)->toContain("'published_at' => 'date|required'")
        ->and($updated)->toContain('@property datetime $published_at');

    @unlink($tmpPath);
});

it('creates direct relation method and updates source class code', function (): void {
    $command = modelMakeCommandWithOutput();
    $updateRelation = new ReflectionMethod(ModelMakeCommand::class, 'updateClassWithNewRelation');
    $getPath = new ReflectionMethod(ModelMakeCommand::class, 'getPath');
    $inputProperty = new ReflectionProperty(Command::class, 'input');
    $updateRelation->setAccessible(true);
    $getPath->setAccessible(true);
    $inputProperty->setAccessible(true);

    $input = new ArrayInput([]);
    $input->setInteractive(false);
    $inputProperty->setValue($command, $input);

    $unique = 'RelCov' . uniqid('', false);
    $sourceClass = "App\\Models\\{$unique}Source";
    $targetClass = "{$unique}Target";
    $fullTargetClass = "App\\Models\\{$targetClass}";

    $sourcePath = $getPath->invoke($command, $sourceClass);
    $targetPath = $getPath->invoke($command, $fullTargetClass);
    @mkdir(dirname($sourcePath), 0777, true);
    @mkdir(dirname($targetPath), 0777, true);

    $baseCode = <<<'PHP'
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TempClass extends Model
{
    #region [RELATIONS]
    #endregion
}
PHP;

    file_put_contents($sourcePath, str_replace('TempClass', "{$unique}Source", $baseCode));
    file_put_contents($targetPath, str_replace('TempClass', $targetClass, $baseCode));

    $sourceCode = file_get_contents($sourcePath);
    $updatedSource = $updateRelation->invoke(
        $command,
        $sourceClass,
        $sourceCode,
        $sourcePath,
        'target',
        'relation\\ManyToOne',
        $targetClass,
        true,
    );

    $updatedTarget = file_get_contents($targetPath);

    expect($updatedSource)->toContain('function target():')
        ->and($updatedTarget)->not->toContain('function target():');

    @unlink($sourcePath);
    @unlink($targetPath);
});

it('creates migration command payload based on class state', function (): void {
    $command = modelMakeCommandWithOutput();
    $createMigration = new ReflectionMethod(ModelMakeCommand::class, 'createMigration');
    $isNewClassProperty = new ReflectionProperty(ModelMakeCommand::class, 'isNewClass');
    $inputProperty = new ReflectionProperty(Command::class, 'input');
    $createMigration->setAccessible(true);
    $isNewClassProperty->setAccessible(true);
    $inputProperty->setAccessible(true);

    $unique = 'MigCov' . uniqid('', false);
    $input = new ArrayInput(['name' => $unique, '--pivot' => false]);
    $input->bind($command->getDefinition());
    $inputProperty->setValue($command, $input);

    $isNewClassProperty->setValue($command, true);
    ModelMakeMigrationSpyCommand::$lastArguments = [];
    $application = new SymfonyApplication();
    $application->add(new ModelMakeMigrationSpyCommand());
    $command->setApplication($application);
    $createMigration->invoke($command);

    $table = Illuminate\Support\Str::snake(Illuminate\Support\Str::pluralStudly(class_basename($unique)));
    expect(ModelMakeMigrationSpyCommand::$lastArguments['name'])->toBe('create_' . $table . '_table')
        ->and(ModelMakeMigrationSpyCommand::$lastArguments['create'])->toBe($table)
        ->and(ModelMakeMigrationSpyCommand::$lastArguments['fullpath'])->toBeTrue();
});

it('creates migration payload for update and pivot branches', function (): void {
    $command = modelMakeCommandWithOutput();
    $createMigration = new ReflectionMethod(ModelMakeCommand::class, 'createMigration');
    $isNewClassProperty = new ReflectionProperty(ModelMakeCommand::class, 'isNewClass');
    $inputProperty = new ReflectionProperty(Command::class, 'input');
    $createMigration->setAccessible(true);
    $isNewClassProperty->setAccessible(true);
    $inputProperty->setAccessible(true);

    $application = new SymfonyApplication();
    $application->add(new ModelMakeMigrationSpyCommand());
    $command->setApplication($application);

    $unique = 'MigUpdate' . uniqid('', false);
    $input = new ArrayInput(['name' => $unique, '--pivot' => true]);
    $input->bind($command->getDefinition());
    $inputProperty->setValue($command, $input);
    $isNewClassProperty->setValue($command, false);
    ModelMakeMigrationSpyCommand::$lastArguments = [];

    $createMigration->invoke($command);

    $table = Illuminate\Support\Str::singular(Illuminate\Support\Str::snake(Illuminate\Support\Str::pluralStudly(class_basename($unique))));
    expect(ModelMakeMigrationSpyCommand::$lastArguments['name'])->toBe('update_' . $table . '_table')
        ->and(ModelMakeMigrationSpyCommand::$lastArguments['update'])->toBe($table);
});

it('runs handle for reserved name branch safely', function (): void {
    $command = modelMakeCommandWithOutput();
    $exit = $command->run(new ArrayInput(['name' => 'Class']), new BufferedOutput());

    expect($exit)->toBe(0);
});
