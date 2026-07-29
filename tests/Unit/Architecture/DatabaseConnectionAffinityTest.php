<?php

declare(strict_types=1);

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

const DATABASE_FACADE_CLASS = 'Illuminate\\Support\\Facades\\DB';
const DATABASE_SCHEMA_FACADE_CLASS = 'Illuminate\\Support\\Facades\\Schema';

/**
 * @return list<string>
 */
function database_connection_affinity_facade_calls(string $source, string $relative_path = ''): array
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver);
    $traverser->addVisitor(new ParentConnectingVisitor);
    $statements = $traverser->traverse($statements);

    $finder = new NodeFinder;
    $declared_classes = [];

    foreach ($finder->findInstanceOf($statements, ClassLike::class) as $class) {
        if ($class->namespacedName !== null) {
            $declared_classes[mb_strtolower($class->namespacedName->toString())] = true;
        }
    }

    $calls = [];
    $fingerprint_ordinals = [];

    foreach ($finder->findInstanceOf($statements, StaticCall::class) as $call) {
        if (
            ! $call->class instanceof Name
            || ! database_connection_affinity_is_guarded_call($call)
            || ! database_connection_affinity_is_facade_class($call->class, $declared_classes)
        ) {
            continue;
        }

        $method = $call->name instanceof Identifier
            ? mb_strtolower($call->name->toString())
            : 'dynamic';
        $fingerprint = database_connection_affinity_call_fingerprint($call);
        $fingerprint_ordinals[$fingerprint] = ($fingerprint_ordinals[$fingerprint] ?? 0) + 1;
        $location = "DB::{$method}:{$fingerprint}:{$fingerprint_ordinals[$fingerprint]}";

        $calls[] = $relative_path === '' ? $location : "{$relative_path}:{$location}";
    }

    return $calls;
}

/**
 * @return list<string>
 */
function database_connection_affinity_schema_calls(string $source, string $relative_path = ''): array
{
    $statements = (new ParserFactory)->createForNewestSupportedVersion()->parse($source) ?? [];
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver);
    $traverser->addVisitor(new ParentConnectingVisitor);
    $statements = $traverser->traverse($statements);

    $finder = new NodeFinder;
    $declared_classes = [];

    foreach ($finder->findInstanceOf($statements, ClassLike::class) as $class) {
        if ($class->namespacedName !== null) {
            $declared_classes[mb_strtolower($class->namespacedName->toString())] = true;
        }
    }

    $calls = [];
    $fingerprint_ordinals = [];

    foreach ($finder->findInstanceOf($statements, StaticCall::class) as $call) {
        if (
            ! $call->class instanceof Name
            || ! database_connection_affinity_is_guarded_schema_call($call)
            || ! database_connection_affinity_is_schema_facade_class($call->class, $declared_classes)
        ) {
            continue;
        }

        $method = $call->name instanceof Identifier
            ? mb_strtolower($call->name->toString())
            : 'dynamic';
        $fingerprint = database_connection_affinity_schema_call_fingerprint($call);
        $fingerprint_ordinals[$fingerprint] = ($fingerprint_ordinals[$fingerprint] ?? 0) + 1;
        $location = "Schema::{$method}:{$fingerprint}:{$fingerprint_ordinals[$fingerprint]}";

        $calls[] = $relative_path === '' ? $location : "{$relative_path}:{$location}";
    }

    return $calls;
}

/**
 * @param  array<string, true>  $declared_classes
 */
function database_connection_affinity_is_facade_class(Name $class, array $declared_classes): bool
{
    $resolved_class = $class->toString();

    if (strcasecmp($resolved_class, DATABASE_FACADE_CLASS) === 0) {
        return true;
    }

    return strcasecmp($resolved_class, 'DB') === 0
        && ! isset($declared_classes['db']);
}

/**
 * @param  array<string, true>  $declared_classes
 */
function database_connection_affinity_is_schema_facade_class(Name $class, array $declared_classes): bool
{
    $resolved_class = $class->toString();

    if (strcasecmp($resolved_class, DATABASE_SCHEMA_FACADE_CLASS) === 0) {
        return true;
    }

    return strcasecmp($resolved_class, 'Schema') === 0
        && ! isset($declared_classes['schema']);
}

function database_connection_affinity_call_fingerprint(StaticCall $call): string
{
    $expression = database_connection_affinity_fluent_expression($call);
    $original_class = $call->class;
    $call->class = new FullyQualified(DATABASE_FACADE_CLASS);

    $normalized_call = (new Standard)->prettyPrintExpr($expression);

    $call->class = $original_class;

    return mb_substr(hash('sha256', $normalized_call), 0, 16);
}

function database_connection_affinity_schema_call_fingerprint(StaticCall $call): string
{
    $expression = database_connection_affinity_fluent_expression($call);
    $original_class = $call->class;
    $call->class = new FullyQualified(DATABASE_SCHEMA_FACADE_CLASS);

    $normalized_call = (new Standard)->prettyPrintExpr($expression);

    $call->class = $original_class;

    return mb_substr(hash('sha256', $normalized_call), 0, 16);
}

function database_connection_affinity_fluent_expression(StaticCall $call): Expr
{
    $expression = $call;

    while (
        (($parent = $expression->getAttribute('parent')) instanceof MethodCall || $parent instanceof NullsafeMethodCall)
        && $parent->var === $expression
    ) {
        $expression = $parent;
    }

    return $expression;
}

function database_connection_affinity_is_guarded_call(StaticCall $call): bool
{
    if (! $call->name instanceof Identifier) {
        return true;
    }

    $method = mb_strtolower($call->name->toString());

    if (in_array($method, [
        'connection',
        'connectusing',
        'usingconnection',
        'purge',
        'disconnect',
        'reconnect',
        'setdefaultconnection',
    ], true)) {
        return ! database_connection_affinity_has_explicit_argument($call);
    }

    return ! in_array($method, [
        'raw',
        'build',
        'calculatedynamicconnectionname',
        'getdefaultconnection',
        'supporteddrivers',
        'availabledrivers',
        'extend',
        'forgetextension',
        'getconnections',
        'setreconnector',
        'setapplication',
        'macro',
        'mixin',
        'hasmacro',
        'flushmacros',
        'prohibitdestructivecommands',
    ], true);
}

function database_connection_affinity_is_guarded_schema_call(StaticCall $call): bool
{
    if (! $call->name instanceof Identifier) {
        return true;
    }

    $method = mb_strtolower($call->name->toString());

    if ($method === 'connection') {
        return ! database_connection_affinity_has_explicit_argument($call);
    }

    return ! in_array($method, [
        'defaultstringlength',
        'defaulttimeprecision',
        'defaultmorphkeytype',
        'morphusinguuids',
        'morphusingulids',
        'macro',
        'mixin',
        'hasmacro',
        'flushmacros',
    ], true);
}

function database_connection_affinity_has_explicit_argument(StaticCall $call): bool
{
    if ($call->args === []) {
        return false;
    }

    $argument = $call->args[0]->value;

    if (
        $argument instanceof ConstFetch
        && in_array(mb_strtolower($argument->name->toString()), ['null', 'false'], true)
    ) {
        return false;
    }

    if ($argument instanceof String_) {
        return ! in_array($argument->value, ['', '0'], true);
    }

    if ($argument instanceof Int_) {
        return $argument->value !== 0;
    }

    if ($argument instanceof Float_) {
        return $argument->value !== 0.0;
    }

    return true;
}

/**
 * @return list<string>
 */
function database_connection_affinity_repository_calls(string $project_root): array
{
    $files = [];
    $directories = array_filter([
        $project_root . '/app',
        ...glob($project_root . '/Modules/*/app'),
    ], is_dir(...));

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', mb_substr($path, mb_strlen($project_root) + 1));

            $files = [...$files, ...database_connection_affinity_facade_calls((string) file_get_contents($path), $relative_path)];
        }
    }

    sort($files);

    return $files;
}

/**
 * @return list<string>
 */
function database_connection_affinity_repository_schema_calls(string $project_root): array
{
    $files = [];
    $directories = array_filter([
        $project_root . '/app',
        ...glob($project_root . '/Modules/*/app'),
    ], is_dir(...));

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relative_path = str_replace(DIRECTORY_SEPARATOR, '/', mb_substr($path, mb_strlen($project_root) + 1));

            $files = [...$files, ...database_connection_affinity_schema_calls((string) file_get_contents($path), $relative_path)];
        }
    }

    sort($files);

    return $files;
}

it('detects guarded DB facade calls in executable PHP', function (): void {
    expect(database_connection_affinity_facade_calls('<?php DB::table("users")->get();'))
        ->toBe(['DB::table:c85b2ba163b40dbb:1']);
});

it('uses stable call identifiers when blank lines precede a violation', function (): void {
    $expected = ['app/Action.php:DB::transaction:e87978ec2fbbeb3b:1'];

    expect(database_connection_affinity_facade_calls('<?php DB::transaction(fn () => null);', 'app/Action.php'))
        ->toBe($expected);
    expect(database_connection_affinity_facade_calls("<?php\n\n\nDB::transaction(fn () => null);", 'app/Action.php'))
        ->toBe($expected);
});

it('resolves fully-qualified and imported aliases of the DB facade', function (): void {
    $source = <<<'PHP'
<?php
use Illuminate\Support\Facades\DB as Database;

\Illuminate\Support\Facades\DB::table("users")->get();
Database::transaction(fn () => null);
PHP;

    expect(database_connection_affinity_facade_calls($source, 'app/Action.php'))->toBe([
        'app/Action.php:DB::table:c85b2ba163b40dbb:1',
        'app/Action.php:DB::transaction:e87978ec2fbbeb3b:1',
    ]);
});

it('ignores static calls on local and non-facade DB classes', function (): void {
    $source = <<<'PHP'
<?php
namespace App;

class DB {}

DB::table('users')->get();
\App\DB::transaction(fn () => null);
PHP;

    expect(database_connection_affinity_facade_calls($source))->toBe([]);
});

it('changes the fingerprint when a same-method call is replaced', function (): void {
    expect(database_connection_affinity_facade_calls('<?php DB::table("users")->get();'))
        ->toBe(['DB::table:c85b2ba163b40dbb:1']);
    expect(database_connection_affinity_facade_calls('<?php DB::table("orders")->count();'))
        ->toBe(['DB::table:4cc4fc1ed0bd0463:1']);
});

it('uses an ordinal only to disambiguate identical calls', function (): void {
    $source = <<<'PHP'
<?php
DB::table("users")->get();
DB::table("users")->get();
PHP;

    expect(database_connection_affinity_facade_calls($source))->toBe([
        'DB::table:c85b2ba163b40dbb:1',
        'DB::table:c85b2ba163b40dbb:2',
    ]);
});

it('ignores connection-derived queries, DB raw expressions, and comments', function (): void {
    $source = <<<'PHP'
<?php
$model->getConnection()->table('users')->get();
DB::raw('count(*)');
$example = "DB::table('users')->get();";
// DB::table('users')->get();
PHP;

    expect(database_connection_affinity_facade_calls($source))->toBe([]);
});

it('detects every DB-hitting facade operation and implicit default connection resolution', function (): void {
    $expressions = [
        'connection' => 'DB::connection();',
        'query' => 'DB::query();',
        'select' => 'DB::select("select 1");',
        'selectone' => 'DB::selectOne("select 1");',
        'selectfromwriteconnection' => 'DB::selectFromWriteConnection("select 1");',
        'selectresultsets' => 'DB::selectResultSets("select 1");',
        'cursor' => 'DB::cursor("select 1");',
        'scalar' => 'DB::scalar("select 1");',
        'insert' => 'DB::insert("insert into examples values (1)");',
        'update' => 'DB::update("update examples set id = 1");',
        'delete' => 'DB::delete("delete from examples");',
        'statement' => 'DB::statement("vacuum");',
        'unprepared' => 'DB::unprepared("vacuum");',
        'affectingstatement' => 'DB::affectingStatement("update examples set id = 1");',
        'table' => 'DB::table("examples")->count();',
        'transaction' => 'DB::transaction(fn () => null);',
        'begintransaction' => 'DB::beginTransaction();',
        'commit' => 'DB::commit();',
        'rollback' => 'DB::rollBack();',
    ];

    foreach ($expressions as $method => $expression) {
        $calls = database_connection_affinity_facade_calls("<?php {$expression}");

        expect($calls)->toHaveCount(1)
            ->and($calls[0])->toStartWith("DB::{$method}:");
    }

    expect(database_connection_affinity_facade_calls('<?php DB::connection("explicit");'))
        ->toBe([])
        ->and(database_connection_affinity_facade_calls('<?php DB::connection(null);'))
        ->toHaveCount(1)
        ->and(database_connection_affinity_facade_calls('<?php DB::connection("");'))
        ->toHaveCount(1)
        ->and(database_connection_affinity_facade_calls('<?php DB::raw("count(*)");'))
        ->toBe([]);

    foreach (['false', '"0"', '0', '0.0'] as $falsy_literal) {
        expect(database_connection_affinity_facade_calls("<?php DB::connection({$falsy_literal});"))
            ->toHaveCount(1)
            ->and(database_connection_affinity_facade_calls("<?php DB::reconnect({$falsy_literal});"))
            ->toHaveCount(1);
    }
});

it('detects implicit database Schema facade operations', function (): void {
    $expressions = [
        'create' => 'Schema::create("examples", fn ($table) => null);',
        'table' => 'Schema::table("examples", fn ($table) => null);',
        'drop' => 'Schema::drop("examples");',
        'dropifexists' => 'Schema::dropIfExists("examples");',
        'hastable' => 'Schema::hasTable("examples");',
        'hascolumn' => 'Schema::hasColumn("examples", "id");',
        'hascolumns' => 'Schema::hasColumns("examples", ["id"]);',
        'getcolumns' => 'Schema::getColumns("examples");',
        'getcolumnlisting' => 'Schema::getColumnListing("examples");',
        'getcolumntype' => 'Schema::getColumnType("examples", "id");',
        'getindexes' => 'Schema::getIndexes("examples");',
        'hasindex' => 'Schema::hasIndex("examples", ["id"]);',
        'getforeignkeys' => 'Schema::getForeignKeys("examples");',
        'getviews' => 'Schema::getViews();',
        'gettables' => 'Schema::getTables();',
        'gettypes' => 'Schema::getTypes();',
        'dropcolumns' => 'Schema::dropColumns("examples", ["legacy"]);',
        'dropalltables' => 'Schema::dropAllTables();',
    ];

    foreach ($expressions as $method => $expression) {
        $calls = database_connection_affinity_schema_calls("<?php {$expression}");

        expect($calls)->toHaveCount(1)
            ->and($calls[0])->toStartWith("Schema::{$method}:");
    }

    $aliases = <<<'PHP'
<?php
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Filament\Schemas\Schema;

\Illuminate\Support\Facades\Schema::hasTable('one');
DatabaseSchema::getColumns('two');
Schema::make();
PHP;

    expect(database_connection_affinity_schema_calls($aliases))->toHaveCount(2)
        ->and(database_connection_affinity_schema_calls('<?php Schema::connection("explicit")->hasTable("examples");'))
        ->toBe([])
        ->and(database_connection_affinity_schema_calls('<?php Schema::connection(null);'))
        ->toHaveCount(1)
        ->and(database_connection_affinity_schema_calls('<?php Schema::connection("");'))
        ->toHaveCount(1)
        ->and(database_connection_affinity_schema_calls('<?php Schema::connection($connection);'))
        ->toBe([]);

    foreach (['false', '"0"', '0', '0.0'] as $falsy_literal) {
        expect(database_connection_affinity_schema_calls("<?php Schema::connection({$falsy_literal});"))
            ->toHaveCount(1);
    }
});

it('conservatively detects dynamic DB and Schema facade methods', function (): void {
    expect(database_connection_affinity_facade_calls('<?php DB::$method();'))
        ->toHaveCount(1)
        ->and(database_connection_affinity_schema_calls('<?php Schema::$method();'))
        ->toHaveCount(1);
});

it('keeps the runtime DB facade connection-affinity baseline empty', function (): void {
    $project_root = dirname(__DIR__, 5);
    $baseline = require $project_root . '/Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php';

    expect($baseline)->toBe([])
        ->and(database_connection_affinity_repository_calls($project_root))->toBe([])
        ->and(database_connection_affinity_repository_schema_calls($project_root))->toBe([]);
});
