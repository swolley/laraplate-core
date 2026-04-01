<?php

declare(strict_types=1);

use Illuminate\Console\OutputStyle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Console\ClearExpiredModels;
use Modules\Core\Helpers\HelpersCache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ClearExpiredModelsSoftStub extends Model
{
    use SoftDeletes;

    protected $table = 'clear_expired_soft_stubs';

    protected $guarded = [];
}

final class ClearExpiredModelsNoSoftStub extends Model
{
    protected $table = 'clear_expired_no_soft_stubs';
}

function clearExpiredModelsCommandWithOutput(ClearExpiredModels $command): ClearExpiredModels
{
    $output = new OutputStyle(new ArrayInput([]), new BufferedOutput());
    $output_reflection = new ReflectionProperty(Illuminate\Console\Command::class, 'output');
    $output_reflection->setValue($command, $output);

    return $command;
}

it('clears expired soft-deleted rows when configured and skips when disabled', function (): void {
    HelpersCache::clearModels();

    Schema::create('clear_expired_soft_stubs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->timestamp('deleted_at')->nullable();
    });
    Schema::create('clear_expired_no_soft_stubs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
    });

    ClearExpiredModelsSoftStub::query()->insert([
        ['id' => 1, 'deleted_at' => now()->subDays(10)],
        ['id' => 2, 'deleted_at' => now()->subDay()],
        ['id' => 3, 'deleted_at' => null],
    ]);

    HelpersCache::setModels('active', [
        ClearExpiredModelsSoftStub::class,
        ClearExpiredModelsNoSoftStub::class,
    ]);

    config(['core.soft_deletes_expiration_days' => 5]);
    $command = clearExpiredModelsCommandWithOutput(new ClearExpiredModels());
    $command->handle();

    expect(ClearExpiredModelsSoftStub::withTrashed()->whereKey(1)->exists())->toBeFalse()
        ->and(ClearExpiredModelsSoftStub::withTrashed()->whereKey(2)->exists())->toBeTrue()
        ->and(ClearExpiredModelsSoftStub::withTrashed()->whereKey(3)->exists())->toBeTrue();

    config(['core.soft_deletes_expiration_days' => null]);
    $command->handle();

    Schema::dropIfExists('clear_expired_no_soft_stubs');
    Schema::dropIfExists('clear_expired_soft_stubs');
    HelpersCache::clearModels();
});
