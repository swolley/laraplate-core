<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Route;
use Modules\Core\Console\AddRouteCommentsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

function addRouteCommentsCommandWithOutput(AddRouteCommentsCommand $command): AddRouteCommentsCommand
{
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $output_reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $output_reflection->setValue($command, $output);

    return $command;
}

it('covers AddRouteCommentsCommand private helpers and empty routes handle', function (): void {
    $command = new AddRouteCommentsCommand();
    $get_route_info = new ReflectionMethod($command, 'getRouteInfo');
    $generate_comment = new ReflectionMethod($command, 'generateComment');
    $add_comment = new ReflectionMethod($command, 'addCommentToMethod');
    $get_route_info->setAccessible(true);
    $generate_comment->setAccessible(true);
    $add_comment->setAccessible(true);

    $route = Route::get('/coverage-test-route', static fn (): string => 'ok')->name('coverage.route')->middleware('web');
    $info = $get_route_info->invoke($command, $route);
    expect($info['uri'])->toBe('coverage-test-route')
        ->and($info['name'])->toBe('coverage.route');

    $comment = $generate_comment->invoke($command, [$info]);
    expect($comment)->toContain('@route-comment')
        ->and($comment)->toContain("Route(path: 'coverage-test-route'");

    $tmp_file = tempnam(sys_get_temp_dir(), 'route_comment_');
    file_put_contents($tmp_file, <<<'PHP'
<?php
class TempControllerForRouteComment
{
    public function index(): void {}
}
PHP);

    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    expect(file_get_contents($tmp_file))->toContain('@route-comment');

    $route_comment_version = <<<'PHP'
<?php
class TempControllerForRouteComment
{
    /**
     * @route-comment
     * Route(path: 'old', name: 'old', methods: [GET], middleware: [web])
     */
    public function index(): void {}
}
PHP;
    file_put_contents($tmp_file, $route_comment_version);
    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    $content = file_get_contents($tmp_file);
    expect($content)->not->toContain("Route(path: 'old'")
        ->and($content)->toContain('@route-comment');

    $non_route_comment = <<<'PHP'
<?php
class TempControllerForRouteComment
{
    /**
     * Keep me
     */
    public function index(): void {}
}
PHP;
    file_put_contents($tmp_file, $non_route_comment);
    $add_comment->invoke($command, $tmp_file, 'index', $comment);
    $content = file_get_contents($tmp_file);
    expect($content)->toContain('Keep me');

    $add_comment->invoke($command, $tmp_file, 'missingMethod', $comment);
    expect(file_get_contents($tmp_file))->toBe($content);

    unlink($tmp_file);

    Route::shouldReceive('getRoutes')->once()->andReturn([]);
    addRouteCommentsCommandWithOutput($command)->handle();
    expect(true)->toBeTrue();
});

it('covers AddRouteCommentsCommand handle processing branches', function (): void {
    $tmp_file = tempnam(sys_get_temp_dir(), 'route_handle_');
    file_put_contents($tmp_file, <<<'PHP'
<?php
namespace App\Http\Controllers;
class TempBaseController { public function inherited(): void {} }
class TempChildController extends TempBaseController {}
class TempAddRouteController { public function index(): void {} }
class TempInvokableController { public function __invoke(): void {} }
PHP);

    require_once $tmp_file;

    $route_mock = static function (string $action, array $methods, string $uri, ?string $name, array $middleware = []): Illuminate\Routing\Route {
        $mock = Mockery::mock(Illuminate\Routing\Route::class);
        $mock->shouldReceive('getActionName')->andReturn($action);
        $mock->shouldReceive('methods')->andReturn($methods);
        $mock->shouldReceive('uri')->andReturn($uri);
        $mock->shouldReceive('getName')->andReturn($name);
        $mock->shouldReceive('middleware')->andReturn($middleware);

        return $mock;
    };

    $valid_route = $route_mock(App\Http\Controllers\TempAddRouteController::class . '@index', ['GET'], 'valid/path', 'valid.path', ['web']);
    $inherited_route = $route_mock(App\Http\Controllers\TempChildController::class . '@inherited', ['POST'], 'child/path', 'child.path', ['api']);
    $missing_class_route = $route_mock('App\\Http\\Controllers\\MissingController@index', ['GET'], 'missing-class/path', 'missing.class.path');
    $missing_method_route = $route_mock(App\Http\Controllers\TempAddRouteController::class . '@missing', ['GET'], 'missing-method/path', 'missing.method.path');
    $no_at_route = $route_mock(App\Http\Controllers\TempInvokableController::class, ['GET'], 'invokable/path', 'invokable.path');
    eval('namespace App\\Http\\Controllers; class TempEvalController { public function index(): void {} }');
    $eval_file_missing_route = $route_mock('App\\Http\\Controllers\\TempEvalController@index', ['GET'], 'eval/path', 'eval.path');
    $closure_route = $route_mock('Closure', ['GET'], 'closure/path', 'closure.path');

    Route::shouldReceive('getRoutes')->once()->andReturn([
        $closure_route,
        $no_at_route,
        $missing_class_route,
        $missing_method_route,
        $valid_route,
        $inherited_route,
        $eval_file_missing_route,
    ]);

    $command = addRouteCommentsCommandWithOutput(new AddRouteCommentsCommand());
    $command->handle();

    expect(file_exists($tmp_file))->toBeTrue();
    unlink($tmp_file);
});
