<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as BaseCommand;

/**
 * Runs the command and returns its exit code together with the captured output.
 *
 * The rendered table reaches the buffer as a single write, and chaining several
 * `expectsOutputToContain()` assertions only ever consumes the first one.
 *
 * @param  array<string, mixed>  $parameters
 * @return array{0: int, 1: string}
 */
function runRouteCheck(array $parameters): array
{
    $exit_code = Artisan::call('route:check', $parameters);

    return [$exit_code, Artisan::output()];
}

it('resolves a url to the route that actually handles it', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/crud/select/cms/contents']);

    expect($exit_code)->toBe(BaseCommand::SUCCESS)
        ->and($output)->toContain('core.crud.list')
        ->and($output)->toContain('/app/crud/select/{module}/{entity}')
        ->and($output)->toContain('Modules\Core\Http\Controllers\CrudController@list')
        ->and($output)->toContain('module=cms, entity=contents');
});

it('reports the module specific route instead of the core crud catch-all', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/crud/select/ai/conversations']);

    expect($exit_code)->toBe(BaseCommand::SUCCESS)
        ->and($output)->toContain('ai.crud.conversations.list')
        ->and($output)->toContain('ChatController@listConversations')
        ->and($output)->not->toContain('core.crud.list');
});

it('accepts a leading slash and reports bound parameters and constraints', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => '/app/translations/it']);

    expect($exit_code)->toBe(BaseCommand::SUCCESS)
        ->and($output)->toContain('core.info.translations')
        ->and($output)->toContain('lang=it')
        ->and($output)->toContain('lang=[a-z]{2}');
});

it('matches against the requested http method', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/crud/insert/cms/contents', '--method' => 'post']);

    expect($exit_code)->toBe(BaseCommand::SUCCESS)
        ->and($output)->toContain('core.crud.insert');
});

it('outputs json when asked', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'api/v1/select/cms/contents', '--json' => true]);

    expect($exit_code)->toBe(BaseCommand::SUCCESS);

    $payload = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($payload)->toMatchArray([
        'request' => 'GET /api/v1/select/cms/contents',
        'name' => 'core.api.list',
        'uri' => '/api/v1/select/{module}/{entity}',
        'action' => 'Modules\Core\Http\Controllers\CrudController@list',
        'parameters' => ['module' => 'cms', 'entity' => 'contents'],
    ])
        ->and($payload['methods'])->toContain('GET')
        ->and($payload['middleware'])->toBe(['api', 'crud_api']);
});

it('prompts for the url when the argument is missing', function (): void {
    $this->artisan('route:check')
        ->expectsQuestion('Which relative URL should be resolved?', 'app/crud/select/ai/conversations')
        ->expectsOutputToContain('ai.crud.conversations.list')
        ->assertExitCode(BaseCommand::SUCCESS);
});

it('fails when no route matches the url', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/there/is/no/such/route/here']);

    expect($exit_code)->toBe(BaseCommand::FAILURE)
        ->and($output)->toContain('No route matches');
});

it('fails and lists the allowed methods when only the method is wrong', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/crud/insert/cms/contents', '--method' => 'DELETE']);

    expect($exit_code)->toBe(BaseCommand::FAILURE)
        ->and($output)->toContain('registered for')
        ->and($output)->toContain('POST');
});

it('rejects an unsupported http method', function (): void {
    [$exit_code, $output] = runRouteCheck(['url' => 'app/about', '--method' => 'FOO']);

    expect($exit_code)->toBe(BaseCommand::FAILURE)
        ->and($output)->toContain('Unsupported HTTP method');
});
