<?php

declare(strict_types=1);

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravel\Prompts\ConfirmPrompt;
use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Modules\Core\Console\MakeModelTranslatableCommand;
use Modules\Core\Tests\Fixtures\BareClass;
use Modules\Core\Tests\Fixtures\FakeArticle;
use Modules\Core\Tests\Fixtures\FakeModulePost;
use Modules\Core\Tests\Fixtures\HandleTestContext;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require_once dirname(__DIR__, 2) . '/Fixtures/handle_test_overrides.php';

/*
|--------------------------------------------------------------------------
| Helper: resolve a path relative to the module root
|--------------------------------------------------------------------------
*/

function stubPath(string $relative): string
{
    return dirname(__DIR__, 3) . '/' . mb_ltrim($relative, '/');
}

/**
 * Create a command instance with a buffered output so that
 * $this->line() / $this->info() / $this->error() / $this->warn() work.
 */
function commandWithOutput(): MakeModelTranslatableCommand
{
    $command = new MakeModelTranslatableCommand();
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $ref = new ReflectionProperty(Command::class, 'output');
    $ref->setValue($command, $output);

    return $command;
}

function sampleColumns(): array
{
    return [
        ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false],
        ['name' => 'body', 'type_name' => 'text', 'type' => 'text', 'nullable' => true],
        ['name' => 'metadata', 'type_name' => 'json', 'type' => 'json', 'nullable' => false],
    ];
}

// ---------------------------------------------------------------------------
// Structural tests
// ---------------------------------------------------------------------------

it('command exists and has correct signature', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("'make:model-translatable'");
    expect($source)->toContain('Make an existing model translatable');
});

it('command class has correct properties', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);

    expect($reflection->getName())->toBe('Modules\Core\Console\MakeModelTranslatableCommand');
    expect($reflection->isSubclassOf(Command::class))->toBeTrue();
});

it('command can be instantiated', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);

    expect($reflection->isInstantiable())->toBeTrue();
});

it('command has handle method that returns int', function (): void {
    $reflection = new ReflectionMethod(MakeModelTranslatableCommand::class, 'handle');

    expect($reflection->getReturnType()->getName())->toBe('int');
});

it('command uses Laravel Prompts', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Laravel\\Prompts\\confirm');
    expect($source)->toContain('Laravel\\Prompts\\multiselect');
    expect($source)->toContain('Laravel\\Prompts\\select');
});

it('command uses Schema for database inspection', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('Schema::getColumns');
    expect($source)->toContain('Schema::hasTable');
});

it('command references HasTranslations trait', function (): void {
    $reflection = new ReflectionClass(MakeModelTranslatableCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('HasTranslations::class');
    expect($source)->toContain("'use HasTranslations;'");
});

// ---------------------------------------------------------------------------
// isTranslatableColumn
// ---------------------------------------------------------------------------

describe('isTranslatableColumn', function (): void {
    beforeEach(function (): void {
        $this->command = new MakeModelTranslatableCommand();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'isTranslatableColumn');
    });

    it('accepts varchar columns', function (): void {
        $col = ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts text columns', function (): void {
        $col = ['name' => 'body', 'type_name' => 'text', 'type' => 'text', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts json columns', function (): void {
        $col = ['name' => 'components', 'type_name' => 'json', 'type' => 'json', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts jsonb columns', function (): void {
        $col = ['name' => 'metadata', 'type_name' => 'jsonb', 'type' => 'jsonb', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts mediumtext columns', function (): void {
        $col = ['name' => 'description', 'type_name' => 'mediumtext', 'type' => 'mediumtext', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts longtext columns', function (): void {
        $col = ['name' => 'content', 'type_name' => 'longtext', 'type' => 'longtext', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('accepts PostgreSQL character varying columns', function (): void {
        $col = ['name' => 'name', 'type_name' => 'character varying', 'type' => 'character varying(255)', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeTrue();
    });

    it('rejects excluded columns by name', function (): void {
        $excluded = ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token', 'locale'];

        foreach ($excluded as $name) {
            $col = ['name' => $name, 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false];
            expect($this->method->invoke($this->command, $col))->toBeFalse("Column '{$name}' should be excluded");
        }
    });

    it('rejects foreign key columns ending with _id', function (): void {
        $col = ['name' => 'author_id', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false];
        expect($this->method->invoke($this->command, $col))->toBeFalse();
    });

    it('rejects auto-increment columns', function (): void {
        $col = ['name' => 'counter', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => true];
        expect($this->method->invoke($this->command, $col))->toBeFalse();
    });

    it('rejects non-translatable types', function (): void {
        $types = ['integer', 'bigint', 'boolean', 'datetime', 'timestamp', 'float', 'decimal', 'date', 'blob'];

        foreach ($types as $type) {
            $col = ['name' => 'field', 'type_name' => $type, 'type' => $type, 'auto_increment' => false];
            expect($this->method->invoke($this->command, $col))->toBeFalse("Type '{$type}' should not be translatable");
        }
    });
});

// ---------------------------------------------------------------------------
// columnToBlueprintCode
// ---------------------------------------------------------------------------

describe('columnToBlueprintCode', function (): void {
    beforeEach(function (): void {
        $this->command = new MakeModelTranslatableCommand();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'columnToBlueprintCode');
    });

    it('generates string without explicit length for default varchar(255)', function (): void {
        $col = ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))
            ->toContain("\$table->string('title')")
            ->not->toContain('255');
    });

    it('generates string with explicit length for non-default varchar', function (): void {
        $col = ['name' => 'code', 'type_name' => 'varchar', 'type' => 'varchar(50)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->string('code', 50)");
    });

    it('generates text for text type', function (): void {
        $col = ['name' => 'body', 'type_name' => 'text', 'type' => 'text', 'nullable' => true];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->text('body')");
    });

    it('generates mediumText for mediumtext type', function (): void {
        $col = ['name' => 'desc', 'type_name' => 'mediumtext', 'type' => 'mediumtext', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->mediumText('desc')");
    });

    it('generates longText for longtext type', function (): void {
        $col = ['name' => 'content', 'type_name' => 'longtext', 'type' => 'longtext', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->longText('content')");
    });

    it('generates json for json type', function (): void {
        $col = ['name' => 'components', 'type_name' => 'json', 'type' => 'json', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->json('components')");
    });

    it('generates json for jsonb type', function (): void {
        $col = ['name' => 'data', 'type_name' => 'jsonb', 'type' => 'jsonb', 'nullable' => true];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->json('data')");
    });

    it('generates string with custom length for PostgreSQL character varying', function (): void {
        $col = ['name' => 'name', 'type_name' => 'character varying', 'type' => 'character varying(100)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->string('name', 100)");
    });

    it('generates char for char type', function (): void {
        $col = ['name' => 'code', 'type_name' => 'char', 'type' => 'char(2)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->char('code', 2)");
    });

    it('generates char for bpchar type', function (): void {
        $col = ['name' => 'flag', 'type_name' => 'bpchar', 'type' => 'bpchar(3)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->char('flag', 3)");
    });

    it('generates tinyText for tinytext type', function (): void {
        $col = ['name' => 'excerpt', 'type_name' => 'tinytext', 'type' => 'tinytext', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->tinyText('excerpt')");
    });

    it('falls back to string for unknown types', function (): void {
        $col = ['name' => 'misc', 'type_name' => 'nvarchar', 'type' => 'nvarchar(100)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("\$table->string('misc', 100)");
    });

    it('sets nullable(false) for non-nullable columns', function (): void {
        $col = ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain('->nullable(false)');
    });

    it('sets nullable(true) for nullable columns', function (): void {
        $col = ['name' => 'subtitle', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => true];

        expect($this->method->invoke($this->command, $col))->toContain('->nullable(true)');
    });

    it('adds translated comment', function (): void {
        $col = ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))->toContain("->comment('The translated title')");
    });

    it('builds a complete valid Blueprint statement', function (): void {
        $col = ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false];

        expect($this->method->invoke($this->command, $col))
            ->toBe("\$table->string('title')->nullable(false)->comment('The translated title');");
    });
});

// ---------------------------------------------------------------------------
// removeFieldsFromModel
// ---------------------------------------------------------------------------

describe('removeFieldsFromModel', function (): void {
    beforeEach(function (): void {
        $this->command = new MakeModelTranslatableCommand();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'removeFieldsFromModel');
    });

    it('removes fields from fillable array', function (): void {
        $content = <<<'PHP'
    protected $fillable = [
        'title',
        'slug',
        'is_published',
    ];
PHP;
        $result = $this->method->invoke($this->command, $content, ['title', 'slug']);

        expect($result)->not->toContain("'title'")
            ->not->toContain("'slug'")
            ->toContain("'is_published'");
    });

    it('removes fields from casts method', function (): void {
        $content = <<<'PHP'
    protected function casts(): array
    {
        return [
            'title' => 'string',
            'components' => 'array',
            'is_published' => 'boolean',
        ];
    }
PHP;
        $result = $this->method->invoke($this->command, $content, ['title', 'components']);

        expect($result)->not->toContain("'title'")
            ->not->toContain("'components'")
            ->toContain("'is_published' => 'boolean'");
    });

    it('removes fields from casts property', function (): void {
        $content = <<<'PHP'
    protected $casts = [
        'title' => 'string',
        'data' => 'array',
        'active' => 'boolean',
    ];
PHP;
        $result = $this->method->invoke($this->command, $content, ['title', 'data']);

        expect($result)->not->toContain("'title'")
            ->not->toContain("'data'")
            ->toContain("'active' => 'boolean'");
    });

    it('does not remove fields outside fillable or casts context', function (): void {
        $content = <<<'PHP'
    protected $fillable = [
        'title',
        'other',
    ];

    public function getTitle(): string
    {
        return $this->title;
    }
PHP;
        $result = $this->method->invoke($this->command, $content, ['title']);

        expect($result)->toContain('return $this->title;')
            ->toContain("'other'");
    });

    it('preserves structure when no fields match', function (): void {
        $content = <<<'PHP'
    protected $fillable = [
        'name',
        'email',
    ];
PHP;

        expect($this->method->invoke($this->command, $content, ['nonexistent']))->toBe($content);
    });

    it('handles both fillable and casts in same content', function (): void {
        $content = <<<'PHP'
    protected $fillable = [
        'title',
        'slug',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'title' => 'string',
            'is_active' => 'boolean',
        ];
    }
PHP;
        $result = $this->method->invoke($this->command, $content, ['title']);

        expect(mb_substr_count($result, "'title'"))->toBe(0);
        expect($result)->toContain("'slug'");
        expect(mb_substr_count($result, "'is_active'"))->toBe(2);
    });

    it('handles empty fillable array', function (): void {
        $content = <<<'PHP'
    protected $fillable = [
    ];
PHP;

        expect($this->method->invoke($this->command, $content, ['title']))->toBe($content);
    });
});

// ---------------------------------------------------------------------------
// Migration stub
// ---------------------------------------------------------------------------

describe('migration stub', function (): void {
    it('file exists', function (): void {
        expect(file_exists(stubPath('stubs/make_model_translatable_migration.stub')))->toBeTrue();
    });

    it('contains all required placeholders', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));

        expect($stub)
            ->toContain('[TRANSLATION_TABLE_NAME]')
            ->toContain('[MODEL_FK]')
            ->toContain('[MODEL_TABLE]')
            ->toContain('[MODEL_SINGULAR]')
            ->toContain('[TRANSLATED_FIELDS]')
            ->toContain('[INSERT_FIELDS]')
            ->toContain('[DROP_COLUMNS]')
            ->toContain('[RESTORE_COLUMNS]')
            ->toContain('[RESTORE_FIELDS]');
    });

    it('has proper migration structure', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));

        expect($stub)
            ->toContain('extends Migration')
            ->toContain('public function up(): void')
            ->toContain('public function down(): void')
            ->toContain('Schema::create(')
            ->toContain('MigrateUtils::timestamps($table)');
    });

    it('creates table with locale, foreign key, and unique constraint', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));

        expect($stub)
            ->toContain("->string('locale', 10)")
            ->toContain('->foreignId(')
            ->toContain('->cascadeOnDelete()')
            ->toContain('->unique(');
    });

    it('migrates data with chunk for performance', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));

        expect($stub)
            ->toContain("config('app.locale')")
            ->toContain('->chunk(500,')
            ->toContain('->insert($inserts)');
    });

    it('drops columns from original table in up()', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));
        $up = mb_substr($stub, 0, mb_strpos($stub, 'public function down'));

        expect($up)->toContain('[DROP_COLUMNS]');
    });

    it('restores columns and data in down()', function (): void {
        $stub = file_get_contents(stubPath('stubs/make_model_translatable_migration.stub'));
        $down = mb_substr($stub, mb_strpos($stub, 'public function down'));

        expect($down)
            ->toContain('[RESTORE_COLUMNS]')
            ->toContain('[RESTORE_FIELDS]')
            ->toContain('Schema::dropIfExists(')
            ->toContain('->update(');
    });
});

// ---------------------------------------------------------------------------
// Translation model stub
// ---------------------------------------------------------------------------

describe('translation model stub', function (): void {
    it('file exists', function (): void {
        expect(file_exists(stubPath('stubs/translation.stub')))->toBeTrue();
    });

    it('contains all required placeholders', function (): void {
        $stub = file_get_contents(stubPath('stubs/translation.stub'));

        expect($stub)
            ->toContain('[TRANSLATION_NAMESPACE]')
            ->toContain('[TRANSLATION_CLASS_NAME]')
            ->toContain('[MODEL_FULL_NAME]')
            ->toContain('[MODEL_CLASS_NAME]')
            ->toContain('[MODEL_RELATION]')
            ->toContain('[MODEL_FK]')
            ->toContain('[FILLABLE_ATTTRIBUTES]')
            ->toContain('[CASTS_ATTRIBUTES]')
            ->toContain('[HIDDEN_ATTRIBUTES]');
    });
});

// ---------------------------------------------------------------------------
// Command path resolution logic
// ---------------------------------------------------------------------------

describe('command handles path resolution for modules and app', function (): void {
    it('detects module namespace and uses module_path', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain("Str::startsWith(\$model_full_name, 'Modules\\\\')");
        expect($source)->toContain('module_path($module');
    });

    it('falls back to app_path and database_path for non-module models', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain("app_path('Models/Translations/')");
        expect($source)->toContain("database_path('migrations/')");
    });

    it('uses ReflectionClass for model file path discovery', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('$reflection->getFileName()');
    });
});

// ---------------------------------------------------------------------------
// Command adds ITranslated interface
// ---------------------------------------------------------------------------

describe('command adds ITranslated interface to generated model', function (): void {
    it('replaces extends declaration to include ITranslated', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)
            ->toContain("'extends Model'")
            ->toContain("'extends Model implements ITranslated'");
    });

    it('injects ITranslated import', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('Modules\\\\Core\\\\Services\\\\Translation\\\\Definitions\\\\ITranslated');
    });
});

// ---------------------------------------------------------------------------
// Command validates preconditions
// ---------------------------------------------------------------------------

describe('command validates preconditions', function (): void {
    it('checks source table exists', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('Schema::hasTable($table_name)')
            ->toContain('does not exist. Run migrations first');
    });

    it('checks translation table does not already exist', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('Schema::hasTable($translation_table)')
            ->toContain('already exists');
    });

    it('checks for empty translatable columns', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('No translatable (string/text/json) columns found');
    });

    it('shows summary and asks for confirmation before proceeding', function (): void {
        $source = file_get_contents((new ReflectionClass(MakeModelTranslatableCommand::class))->getFileName());

        expect($source)->toContain('Summary of changes')
            ->toContain("confirm('Proceed?'");
    });
});

// ---------------------------------------------------------------------------
// createTranslationModel
// ---------------------------------------------------------------------------

describe('createTranslationModel', function (): void {
    beforeEach(function (): void {
        $this->command = commandWithOutput();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'createTranslationModel');
        $this->tmp_dir = sys_get_temp_dir() . '/laraplate_test_' . uniqid() . '/';
        mkdir($this->tmp_dir, 0755, true);
    });

    afterEach(function (): void {
        $files = glob($this->tmp_dir . '*');

        foreach ($files as $f) {
            unlink($f);
        }

        rmdir($this->tmp_dir);
    });

    it('creates translation model file', function (): void {
        $result = $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        expect($result)->toBe(Command::SUCCESS);
        expect(file_exists($this->tmp_dir . 'FakeArticleTranslation.php'))->toBeTrue();
    });

    it('generated model contains correct namespace', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)->toContain('namespace Modules\\Core\\Tests\\Fixtures\\Translations;');
    });

    it('generated model contains fillable attributes', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)
            ->toContain("'title'")
            ->toContain("'body'")
            ->toContain("'metadata'")
            ->toContain("'article_id'")
            ->toContain("'locale'");
    });

    it('generated model casts json columns to array', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)->toContain("'metadata' => 'array'");
    });

    it('generated model implements ITranslated', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)
            ->toContain('implements ITranslated')
            ->toContain('use Modules\\Core\\Services\\Translation\\Definitions\\ITranslated;');
    });

    it('generated model has correct BelongsTo relation', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)
            ->toContain('function article(): BelongsTo')
            ->toContain('BelongsTo<FakeArticle>')
            ->toContain('FakeArticle::class');
    });

    it('generated model carries over hidden attributes from source model', function (): void {
        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)->toContain("'body',");
        $hidden_start = mb_strpos($content, '$hidden');
        $hidden_end = mb_strpos($content, '];', $hidden_start);
        $hidden_block = mb_substr($content, $hidden_start, $hidden_end - $hidden_start);

        expect($hidden_block)->toContain("'body'");
    });

    it('creates nested directory when it does not exist', function (): void {
        $nested = $this->tmp_dir . 'nested/deep/';

        $result = $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $nested,
        );

        expect($result)->toBe(Command::SUCCESS);
        expect(file_exists($nested . 'FakeArticleTranslation.php'))->toBeTrue();

        unlink($nested . 'FakeArticleTranslation.php');
        rmdir($nested);
        rmdir($this->tmp_dir . 'nested/');
    });

    it('generates empty casts block when no json columns selected', function (): void {
        $string_only_cols = [
            ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false],
        ];

        $this->method->invoke(
            $this->command,
            FakeArticle::class,
            'FakeArticle',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\FakeArticleTranslation',
            'FakeArticleTranslation',
            'article_id',
            'article',
            $string_only_cols,
            $this->tmp_dir,
        );

        $content = file_get_contents($this->tmp_dir . 'FakeArticleTranslation.php');

        expect($content)->not->toContain("=> 'array'");
    });

    it('handles model without explicit hidden property', function (): void {
        $result = $this->method->invoke(
            $this->command,
            Model::class,
            'Model',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\ModelTranslation',
            'ModelTranslation',
            'model_id',
            'model',
            [['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false]],
            $this->tmp_dir,
        );

        expect($result)->toBe(Command::SUCCESS);

        $content = file_get_contents($this->tmp_dir . 'ModelTranslation.php');
        $hidden_start = mb_strpos($content, '$hidden');
        $hidden_end = mb_strpos($content, '];', $hidden_start);
        $hidden_block = mb_substr($content, $hidden_start, $hidden_end - $hidden_start);

        expect($hidden_block)->not->toContain("'title'");
    });

    it('defaults to empty hidden when class has no hidden property at all', function (): void {
        $result = $this->method->invoke(
            $this->command,
            BareClass::class,
            'BareClass',
            'Modules\\Core\\Tests\\Fixtures\\Translations\\BareClassTranslation',
            'BareClassTranslation',
            'bare_item_id',
            'bare_item',
            [['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'nullable' => false]],
            $this->tmp_dir,
        );

        expect($result)->toBe(Command::SUCCESS);

        $content = file_get_contents($this->tmp_dir . 'BareClassTranslation.php');
        $hidden_start = mb_strpos($content, '$hidden');
        $hidden_end = mb_strpos($content, '];', $hidden_start);
        $hidden_block = mb_substr($content, $hidden_start, $hidden_end - $hidden_start);

        expect($hidden_block)->not->toContain("'title'");
    });
});

// ---------------------------------------------------------------------------
// createTranslatableMigration
// ---------------------------------------------------------------------------

describe('createTranslatableMigration', function (): void {
    beforeEach(function (): void {
        $this->command = commandWithOutput();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'createTranslatableMigration');
        $this->tmp_dir = sys_get_temp_dir() . '/laraplate_mig_' . uniqid() . '/';
        mkdir($this->tmp_dir, 0755, true);
    });

    afterEach(function (): void {
        $files = glob($this->tmp_dir . '*');

        foreach ($files as $f) {
            unlink($f);
        }

        rmdir($this->tmp_dir);
    });

    it('creates a migration file with correct name pattern', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $files = glob($this->tmp_dir . '*_create_article_translations_table.php');

        expect($files)->toHaveCount(1);
    });

    it('migration contains correct table name', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)
            ->toContain("Schema::create('article_translations'")
            ->toContain("Schema::dropIfExists('article_translations')");
    });

    it('migration contains foreign key definition', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)
            ->toContain("->foreignId('article_id')")
            ->toContain("->constrained('articles'")
            ->toContain('->cascadeOnDelete()');
    });

    it('migration contains translated field definitions', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)
            ->toContain("\$table->string('title')")
            ->toContain("\$table->text('body')")
            ->toContain("\$table->json('metadata')");
    });

    it('migration contains data insertion fields', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)
            ->toContain("'title' => \$row->title")
            ->toContain("'body' => \$row->body")
            ->toContain("'metadata' => \$row->metadata");
    });

    it('migration drops columns from original table', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)->toContain('$table->dropColumn(');
        expect($content)->toContain("'title'");
        expect($content)->toContain("'body'");
        expect($content)->toContain("'metadata'");
    });

    it('migration down restores columns and data', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);
        $down = mb_substr($content, mb_strpos($content, 'function down'));

        expect($down)
            ->toContain("'title' => \$translation->title")
            ->toContain("'body' => \$translation->body")
            ->toContain("'metadata' => \$translation->metadata");
    });

    it('migration has unique constraint on FK and locale', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)->toContain("->unique(['article_id', 'locale']");
    });

    it('migration contains no unresolved placeholders', function (): void {
        $this->method->invoke(
            $this->command,
            'articles',
            'article_translations',
            'FakeArticleTranslation',
            'article_id',
            'article',
            sampleColumns(),
            $this->tmp_dir,
        );

        $file = glob($this->tmp_dir . '*.php')[0];
        $content = file_get_contents($file);

        expect($content)->not->toMatch('/\[[A-Z_]+\]/');
    });
});

// ---------------------------------------------------------------------------
// addTraitToModel
// ---------------------------------------------------------------------------

describe('addTraitToModel', function (): void {
    beforeEach(function (): void {
        $this->command = commandWithOutput();
        $this->method = new ReflectionMethod(MakeModelTranslatableCommand::class, 'addTraitToModel');
        $this->fixture_path = (new ReflectionClass(FakeArticle::class))->getFileName();
        $this->backup = file_get_contents($this->fixture_path);
    });

    afterEach(function (): void {
        file_put_contents($this->fixture_path, $this->backup);
    });

    it('adds HasTranslations import to model file', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);

        expect($content)->toContain('use Modules\\Core\\Helpers\\HasTranslations;');
    });

    it('adds use HasTranslations statement inside class body', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);

        expect($content)->toContain('    use HasTranslations;');
    });

    it('removes translated fields from fillable', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);
        $fillable_start = mb_strpos($content, '$fillable');
        $fillable_end = mb_strpos($content, '];', $fillable_start);
        $fillable_block = mb_substr($content, $fillable_start, $fillable_end - $fillable_start);

        expect($fillable_block)->not->toContain("'title'");
        expect($fillable_block)->not->toContain("'body'");
    });

    it('preserves non-translated fields in fillable', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);

        expect($content)->toContain("'slug'");
        expect($content)->toContain("'is_published'");
    });

    it('removes translated fields from casts', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);
        $casts_start = mb_strpos($content, 'function casts');
        $casts_end = mb_strpos($content, '}', mb_strpos($content, 'return [', $casts_start));
        $casts_block = mb_substr($content, $casts_start, $casts_end - $casts_start);

        expect($casts_block)->not->toContain("'title'");
        expect($casts_block)->not->toContain("'body'");
    });

    it('preserves non-translated casts', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $content = file_get_contents($this->fixture_path);

        expect($content)->toContain("'is_published' => 'boolean'");
    });

    it('does not duplicate import on subsequent calls', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title']);
        $this->method->invoke($this->command, FakeArticle::class, ['body']);

        $content = file_get_contents($this->fixture_path);

        expect(mb_substr_count($content, 'use Modules\\Core\\Helpers\\HasTranslations;'))->toBe(1);
        expect(mb_substr_count($content, 'use HasTranslations;'))->toBe(1);
    });

    it('produces valid PHP after modification', function (): void {
        $this->method->invoke($this->command, FakeArticle::class, ['title', 'body']);

        $result = exec('php -l ' . escapeshellarg($this->fixture_path) . ' 2>&1', $output, $exit_code);

        expect($exit_code)->toBe(0);
    });

    it('warns and returns early when file path cannot be determined', function (): void {
        $this->method->invoke($this->command, stdClass::class, ['title']);

        $content = file_get_contents($this->fixture_path);

        expect($content)->toBe($this->backup);
    });
});

// ---------------------------------------------------------------------------
// handle() orchestration
// ---------------------------------------------------------------------------

describe('handle', function (): void {
    beforeEach(function (): void {
        $this->command = commandWithOutput();
        HandleTestContext::$models = [];
        HandleTestContext::$uses_trait = false;
        HandleTestContext::$app_base = '';
        HandleTestContext::$db_base = '';
        HandleTestContext::$module_base = '';
        HandleTestContext::$config = [];
    });

    afterEach(function (): void {
        // Reset Prompt fallback state
        $fallbackProp = new ReflectionProperty(Prompt::class, 'shouldFallback');
        $fallbackProp->setValue(null, false);

        $fallbacksProp = new ReflectionProperty(Prompt::class, 'fallbacks');
        $fallbacksProp->setValue(null, []);

        foreach ([SelectPrompt::class, MultiSelectPrompt::class, ConfirmPrompt::class] as $cls) {
            $fp = new ReflectionProperty($cls, 'shouldFallback');
            $fp->setValue(null, false);

            $fs = new ReflectionProperty($cls, 'fallbacks');
            $fs->setValue(null, []);
        }

        // Reset Schema mock
        $container = Illuminate\Container\Container::getInstance();

        if ($container->bound('db.schema')) {
            $container->forgetInstance('db.schema');
        }

        Schema::clearResolvedInstance();
    });

    it('returns FAILURE when no models are available', function (): void {
        HandleTestContext::$models = [];

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when all models already use HasTranslations', function (): void {
        HandleTestContext::$models = [FakeArticle::class];
        HandleTestContext::$uses_trait = true;

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when all models contain Translation in name', function (): void {
        HandleTestContext::$models = ['App\\Models\\PostTranslation'];

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when selected index does not resolve to a valid model', function (): void {
        HandleTestContext::$models = ['Dummy', FakeArticle::class];
        HandleTestContext::$uses_trait = false;

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 999);

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when table does not exist', function (): void {
        HandleTestContext::$models = ['Dummy', FakeArticle::class];
        HandleTestContext::$uses_trait = false;

        $schema = new class
        {
            public function hasTable(string $t): bool
            {
                return false;
            }

            public function getColumns(string $t): array
            {
                return [];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when no translatable columns found', function (): void {
        HandleTestContext::$models = ['Dummy', FakeArticle::class];

        $schema = new class
        {
            public function hasTable(string $t): bool
            {
                return true;
            }

            public function getColumns(string $t): array
            {
                return [
                    ['name' => 'id', 'type_name' => 'bigint', 'type' => 'bigint', 'auto_increment' => true, 'nullable' => false],
                    ['name' => 'is_active', 'type_name' => 'boolean', 'type' => 'boolean', 'auto_increment' => false, 'nullable' => false],
                    ['name' => 'created_at', 'type_name' => 'datetime', 'type' => 'datetime', 'auto_increment' => false, 'nullable' => true],
                ];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns FAILURE when translation table already exists', function (): void {
        HandleTestContext::$models = ['Dummy', FakeArticle::class];

        $schema = new class
        {
            public function hasTable(string $t): bool
            {
                return true;
            }

            public function getColumns(string $t): array
            {
                return [
                    ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false, 'nullable' => false],
                ];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);
        MultiSelectPrompt::fallbackWhen(true);
        MultiSelectPrompt::fallbackUsing(fn () => ['title']);

        expect($this->command->handle())->toBe(Command::FAILURE);
    });

    it('returns SUCCESS when user aborts at confirmation', function (): void {
        HandleTestContext::$models = ['Dummy', FakeArticle::class];

        $schema = new class
        {
            private array $existing = ['articles'];

            public function hasTable(string $t): bool
            {
                return in_array($t, $this->existing, true);
            }

            public function getColumns(string $t): array
            {
                return [
                    ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false, 'nullable' => false],
                ];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);
        MultiSelectPrompt::fallbackWhen(true);
        MultiSelectPrompt::fallbackUsing(fn () => ['title']);
        ConfirmPrompt::fallbackWhen(true);
        ConfirmPrompt::fallbackUsing(fn () => false);

        expect($this->command->handle())->toBe(Command::SUCCESS);
    });

    it('resolves paths via module_path for Modules namespace models', function (): void {
        $tmp = sys_get_temp_dir() . '/handle_module_' . uniqid();
        mkdir($tmp . '/models/Translations', 0755, true);
        mkdir($tmp . '/migrations', 0755, true);

        HandleTestContext::$models = ['Dummy', FakeModulePost::class];
        HandleTestContext::$module_base = $tmp;
        HandleTestContext::$config = [
            'modules.paths.generator.model.path' => 'models',
            'modules.paths.generator.migration.path' => 'migrations',
        ];

        $schema = new class
        {
            private array $existing = ['posts'];

            public function hasTable(string $t): bool
            {
                return in_array($t, $this->existing, true);
            }

            public function getColumns(string $t): array
            {
                return [
                    ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false, 'nullable' => false],
                    ['name' => 'content', 'type_name' => 'text', 'type' => 'text', 'auto_increment' => false, 'nullable' => true],
                ];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);
        MultiSelectPrompt::fallbackWhen(true);
        MultiSelectPrompt::fallbackUsing(fn () => ['title']);
        ConfirmPrompt::fallbackWhen(true);
        ConfirmPrompt::fallbackUsing(fn () => true);

        $fixture_path = (new ReflectionClass(FakeModulePost::class))->getFileName();
        $fixture_backup = file_get_contents($fixture_path);

        $result = $this->command->handle();

        file_put_contents($fixture_path, $fixture_backup);

        expect($result)->toBe(Command::SUCCESS);

        $model_files = glob($tmp . '/models/Translations/FakeModulePostTranslation.php');
        expect($model_files)->not->toBeEmpty();

        $migration_files = glob($tmp . '/migrations/*_create_post_translations_table.php');
        expect($migration_files)->not->toBeEmpty();

        // Cleanup
        array_map('unlink', glob($tmp . '/models/Translations/*'));
        rmdir($tmp . '/models/Translations');
        rmdir($tmp . '/models');
        array_map('unlink', glob($tmp . '/migrations/*'));
        rmdir($tmp . '/migrations');
        rmdir($tmp);
    });

    it('runs full success path: creates model, migration, and modifies source', function (): void {
        $tmp = sys_get_temp_dir() . '/handle_test_' . uniqid();
        mkdir($tmp . '/app', 0755, true);
        mkdir($tmp . '/database/migrations', 0755, true);

        HandleTestContext::$models = ['Dummy', FakeArticle::class];
        HandleTestContext::$app_base = $tmp . '/app';
        HandleTestContext::$db_base = $tmp . '/database';

        $schema = new class
        {
            private array $existing = ['articles'];

            public function hasTable(string $t): bool
            {
                return in_array($t, $this->existing, true);
            }

            public function getColumns(string $t): array
            {
                return [
                    ['name' => 'title', 'type_name' => 'varchar', 'type' => 'varchar(255)', 'auto_increment' => false, 'nullable' => false],
                    ['name' => 'body', 'type_name' => 'text', 'type' => 'text', 'auto_increment' => false, 'nullable' => true],
                ];
            }
        };
        Illuminate\Container\Container::getInstance()->instance('db.schema', $schema);

        SelectPrompt::fallbackWhen(true);
        SelectPrompt::fallbackUsing(fn () => 1);
        MultiSelectPrompt::fallbackWhen(true);
        MultiSelectPrompt::fallbackUsing(fn () => ['title', 'body']);
        ConfirmPrompt::fallbackWhen(true);
        ConfirmPrompt::fallbackUsing(fn () => true);

        $fixture_path = (new ReflectionClass(FakeArticle::class))->getFileName();
        $fixture_backup = file_get_contents($fixture_path);

        $result = $this->command->handle();

        file_put_contents($fixture_path, $fixture_backup);

        expect($result)->toBe(Command::SUCCESS);

        $model_path = HandleTestContext::$app_base . '/Models/Translations/FakeArticleTranslation.php';
        expect(file_exists($model_path))->toBeTrue();

        $model_content = file_get_contents($model_path);
        expect($model_content)
            ->toContain('FakeArticleTranslation')
            ->toContain('implements ITranslated');

        $migration_files = glob($tmp . '/database/migrations/*_create_article_translations_table.php');
        expect($migration_files)->toHaveCount(1);

        $migration_content = file_get_contents($migration_files[0]);
        expect($migration_content)
            ->toContain("Schema::create('article_translations'")
            ->toContain("'title'")
            ->toContain("'body'");

        // Cleanup
        array_map('unlink', glob($tmp . '/app/Models/Translations/*'));
        rmdir($tmp . '/app/Models/Translations');
        rmdir($tmp . '/app/Models');
        rmdir($tmp . '/app');
        array_map('unlink', glob($tmp . '/database/migrations/*'));
        rmdir($tmp . '/database/migrations');
        rmdir($tmp . '/database');
        rmdir($tmp);
    });
});
