<?php

declare(strict_types=1);

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Enums\CoreTables;
use Modules\Core\SoftDeletes\Console\ModelSoftDeletesAddCommand;
use Modules\Core\SoftDeletes\Console\ModelSoftDeletesRefreshCommand;
use Modules\Core\SoftDeletes\Console\ModelSoftDeletesRemoveCommand;
use Symfony\Component\Console\Application as SymfonyConsoleApplication;

class ConstructorConfiguredSoftDeleteModel extends Model
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setConnection('soft_delete_constructor_connection');
        $this->setTable('soft_delete_constructor_table');
    }
}

it('defines expected command signatures', function (): void {
    $add = new ReflectionClass(ModelSoftDeletesAddCommand::class);
    $remove = new ReflectionClass(ModelSoftDeletesRemoveCommand::class);
    $refresh = new ReflectionClass(ModelSoftDeletesRefreshCommand::class);

    expect(file_get_contents($add->getFileName()))->toContain('model:soft-deletes-add')
        ->and(file_get_contents($remove->getFileName()))->toContain('model:soft-deletes-remove')
        ->and(file_get_contents($refresh->getFileName()))->toContain('model:soft-deletes-refresh');
});

it('remove command includes mandatory purge safeguard for logical deletions', function (): void {
    $reflection = new ReflectionClass(ModelSoftDeletesRemoveCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('logical-deleted records')
        ->and($source)->toContain('Purge those records with hard delete before removing soft delete columns?')
        ->and($source)->toContain('Operation cancelled. No columns were removed.');
});

it('refresh command reconciles trait-schema-setting consistency', function (): void {
    $reflection = new ReflectionClass(ModelSoftDeletesRefreshCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('model:soft-deletes-add')
        ->and($source)->toContain('model:soft-deletes-remove')
        ->and($source)->toContain('soft_deletes_');
});

it('refresh command merges application quiet option without duplicate definition', function (): void {
    $command = app(ModelSoftDeletesRefreshCommand::class);
    $command->setLaravel(app());
    $command->setApplication(new SymfonyConsoleApplication('coverage', '1.0.0'));
    $command->mergeApplicationDefinition();

    expect($command->getDefinition()->hasOption('quiet'))->toBeTrue();
});

it('instantiates the soft-delete model through its constructor', function (): void {
    $command = new ModelSoftDeletesRemoveCommand(new Filesystem);
    $method = new ReflectionMethod($command, 'instantiateModel');
    $model = $method->invoke($command, new ReflectionClass(ConstructorConfiguredSoftDeleteModel::class));

    expect($model)->toBeInstanceOf(ConstructorConfiguredSoftDeleteModel::class)
        ->and($model->getConnectionName())->toBe('soft_delete_constructor_connection')
        ->and($model->getTable())->toBe('soft_delete_constructor_table');
});

it('writes soft-delete settings through the Setting model connection', function (): void {
    $previous_default = config('database.default');
    $resolver = Model::getConnectionResolver();

    config()->set('database.connections.soft_delete_default', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('database.connections.soft_delete_affinity', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    DB::purge('soft_delete_default');
    DB::purge('soft_delete_affinity');
    DB::setDefaultConnection('soft_delete_default');

    Schema::connection('soft_delete_affinity')->create(CoreTables::Settings->value, function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->string('name')->unique();
        $table->text('value')->nullable();
        $table->boolean('encrypted')->default(false);
        $table->json('choices')->nullable();
        $table->string('type');
        $table->string('group_name');
        $table->string('description')->nullable();
        $table->boolean('is_deleted')->default(false);
        $table->timestamps();
    });

    Model::setConnectionResolver(new class($resolver) implements ConnectionResolverInterface
    {
        public function __construct(private readonly ConnectionResolverInterface $resolver) {}

        public function connection($name = null): Illuminate\Database\ConnectionInterface
        {
            return $name === null ? DB::connection('soft_delete_affinity') : $this->resolver->connection($name);
        }

        public function getDefaultConnection(): string
        {
            return $this->resolver->getDefaultConnection();
        }

        public function setDefaultConnection($name): void
        {
            $this->resolver->setDefaultConnection($name);
        }
    });

    $command = new ModelSoftDeletesRemoveCommand(new Filesystem);
    $method = new ReflectionMethod($command, 'upsertSoftDeletesSetting');
    $method->setAccessible(true);

    try {
        expect($method->invoke($command, 'affinity_table', true))->toBeTrue()
            ->and(DB::connection('soft_delete_affinity')->table(CoreTables::Settings->value)
                ->where('name', 'soft_deletes_affinity_table')
                ->exists())->toBeTrue();
    } finally {
        Model::setConnectionResolver($resolver);
        DB::setDefaultConnection($previous_default);
        DB::disconnect('soft_delete_default');
        DB::purge('soft_delete_default');
        DB::disconnect('soft_delete_affinity');
        DB::purge('soft_delete_affinity');
    }
});
