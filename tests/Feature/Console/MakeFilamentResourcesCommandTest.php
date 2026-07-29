<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Core\Console\MakeFilamentResourcesCommand;
use Modules\Core\Helpers\HelpersCache;
use Symfony\Component\Console\Command\Command;

it('rejects laraplate-owned modules', function (): void {
    $this->artisan('filament:make-resources', ['module' => 'Core', '--no-interaction' => true])
        ->expectsOutputToContain('Laraplate-owned')
        ->assertExitCode(Command::FAILURE);
});

it('accepts App and skips existing resources under no-interaction', function (): void {
    $resource_dir = app_path('Filament/Resources/Users');
    $resource_path = $resource_dir.DIRECTORY_SEPARATOR.'UserResource.php';

    File::ensureDirectoryExists($resource_dir);
    File::put($resource_path, "<?php\n\n// probe stub\n");

    try {
        $this->artisan('filament:make-resources', ['module' => 'App', '--no-interaction' => true])
            ->expectsOutputToContain('skipped (exists)')
            ->expectsOutputToContain('Done for [App]')
            ->assertExitCode(Command::SUCCESS);

        expect(File::get($resource_path))->toContain('probe stub');
    } finally {
        File::delete($resource_path);
        File::deleteDirectory(app_path('Filament'));
    }
});

it('excludes pivot models from the generation list', function (): void {
    $pivot_path = app_path('Models/FilamentMakeResourcesPivotProbe.php');

    File::put($pivot_path, <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

final class FilamentMakeResourcesPivotProbe extends Pivot
{
}
PHP);

    HelpersCache::clearModels();

    try {
        $command = app(MakeFilamentResourcesCommand::class);
        $method = new ReflectionMethod(MakeFilamentResourcesCommand::class, 'collectModels');
        $method->setAccessible(true);

        /** @var list<class-string> $models */
        $models = $method->invoke($command, 'App');

        expect($models)
            ->toContain(\App\Models\User::class)
            ->not->toContain(\App\Models\FilamentMakeResourcesPivotProbe::class);
    } finally {
        File::delete($pivot_path);
        HelpersCache::clearModels();
    }
});
