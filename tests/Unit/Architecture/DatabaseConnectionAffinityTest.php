<?php

declare(strict_types=1);

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

const DATABASE_FACADE_CLASS = 'Illuminate\\Support\\Facades\\DB';

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
            || ! $call->name instanceof Identifier
            || ! database_connection_affinity_is_guarded_method($call->name->toString())
            || ! database_connection_affinity_is_facade_class($call->class, $declared_classes)
        ) {
            continue;
        }

        $method = mb_strtolower($call->name->toString());
        $fingerprint = database_connection_affinity_call_fingerprint($call);
        $fingerprint_ordinals[$fingerprint] = ($fingerprint_ordinals[$fingerprint] ?? 0) + 1;
        $location = "DB::{$method}:{$fingerprint}:{$fingerprint_ordinals[$fingerprint]}";

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

function database_connection_affinity_call_fingerprint(StaticCall $call): string
{
    $expression = database_connection_affinity_fluent_expression($call);
    $original_class = $call->class;
    $call->class = new FullyQualified(DATABASE_FACADE_CLASS);

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

function database_connection_affinity_is_guarded_method(string $method): bool
{
    return in_array(mb_strtolower($method), ['table', 'transaction', 'begintransaction', 'commit', 'rollback'], true);
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

it('does not add DB facade connection-affinity violations to the baseline', function (): void {
    $project_root = dirname(__DIR__, 5);
    $baseline = require $project_root . '/Modules/Core/tests/Fixtures/Architecture/database-connection-affinity-baseline.php';

    $new_violations = array_values(array_diff(database_connection_affinity_repository_calls($project_root), $baseline));

    expect($new_violations)->toBe([]);
});
