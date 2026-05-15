<?php

declare(strict_types=1);

namespace Modules\Core\IdeHelper;

use Barryvdh\LaravelIdeHelper\Console\ModelsCommand;
use Barryvdh\LaravelIdeHelper\Contracts\ModelHookInterface;
use Illuminate\Database\Eloquent\Model;
use Override;
use ReflectionClass;

/**
 * Ensures generated model docblocks include @mixin \Eloquent for Intelephense.
 *
 * IdeHelper* classes only carry @method static builder helpers; instance methods such as
 * hasManyThrough() live on {@see Model}. Intelephense does not always apply nested mixins
 * (IdeHelper → Eloquent), so models need a direct @mixin \Eloquent tag.
 */
final class EloquentMixinModelHook implements ModelHookInterface
{
    private const string ELOQUENT_MIXIN = '@mixin \\Eloquent';

    #[Override]
    public function run(ModelsCommand $command, Model $model): void
    {
        $reflection = new ReflectionClass($model);
        $filename = $reflection->getFileName();

        if ($filename === false || ! is_readable($filename)) {
            return;
        }

        $contents = (string) file_get_contents($filename);

        if (str_contains($contents, self::ELOQUENT_MIXIN)) {
            return;
        }

        if (! preg_match('/\* @mixin IdeHelper/', $contents)) {
            return;
        }

        $updated = preg_replace(
            '/(\* @mixin IdeHelper)/',
            "* @mixin \\Eloquent\n     * @mixin IdeHelper",
            $contents,
            1,
        );

        if ($updated === null || $updated === $contents) {
            return;
        }

        file_put_contents($filename, $updated);
        $command->info('Added @mixin \\Eloquent to ' . $filename);
    }
}
