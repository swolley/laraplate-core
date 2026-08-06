<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\DatabaseManager;
use Modules\Core\Models\Role;
use Modules\Core\Models\User;
use Modules\Core\Overrides\Command;
use Modules\Core\Performance\EndpointBenchmarkReport;
use Modules\Core\Performance\EndpointProfiler;
use Override;
use Spatie\Permission\PermissionRegistrar;

/**
 * Stress-tests the CRUD read engine across entities, reporting latency and
 * query counts for the /api/v1 list endpoint.
 *
 * The public CRUD API is enabled for the process, and — unless an existing
 * --user is given — a superadmin is created inside a transaction that is always
 * rolled back, so the run leaves no data behind.
 *
 *   php artisan perf:crud --module=core --entity=users --entity=roles
 */
final class PerfCrudCommand extends Command
{
    #[Override]
    protected $signature = 'perf:crud
        {--module=core : Module the entities belong to}
        {--entity=* : One or more entities to profile (e.g. users roles)}
        {--iterations=20 : Measured iterations per entity}
        {--warmup=3 : Warmup iterations per entity}
        {--user= : Profile as an existing user id instead of a transient superadmin}';

    #[Override]
    protected $description = 'Benchmark the CRUD list engine across entities as a (transient) superadmin <fg=green>(⚡ Modules\Core)</fg=green>';

    public function handle(EndpointProfiler $profiler, AuthFactory $auth, DatabaseManager $db): int
    {
        $entities = array_values(array_filter(
            (array) $this->option('entity'),
            static fn (mixed $entity): bool => is_string($entity) && $entity !== '',
        ));

        if ($entities === []) {
            $this->error('Provide at least one --entity to profile (e.g. --entity=users).');

            return self::FAILURE;
        }

        $module = (string) ($this->option('module') ?: 'core');
        $iterations = max(1, (int) $this->option('iterations'));
        $warmup = max(0, (int) $this->option('warmup'));

        // The public CRUD API is gated behind config; enable it for this process only.
        config(['core.expose_crud_api' => true]);

        $user_id = $this->option('user');

        if (is_string($user_id) && $user_id !== '') {
            $user = $this->resolveUser($auth, $user_id);

            if (! $user instanceof Authenticatable) {
                $this->error(sprintf('No authenticatable user found with id %s.', $user_id));

                return self::FAILURE;
            }

            $reports = $this->profileEntities($profiler, $module, $entities, $iterations, $warmup, $user);
            $this->renderTable($module, $reports);

            return self::SUCCESS;
        }

        $connection = $db->connection((new User())->getConnectionName());
        $connection->beginTransaction();

        try {
            $user = $this->createTransientSuperadmin();
            $reports = $this->profileEntities($profiler, $module, $entities, $iterations, $warmup, $user);
        } finally {
            $connection->rollBack();
        }

        $this->renderTable($module, $reports);

        return self::SUCCESS;
    }

    private function createTransientSuperadmin(): User
    {
        $role = Role::query()->firstOrCreate([
            'name' => config('permission.roles.superadmin'),
            'guard_name' => 'web',
        ]);

        /** @var User $user */
        $user = User::factory()->create();
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->load('roles');

        return $user;
    }

    /**
     * @param  list<string>  $entities
     * @return list<array{entity:string,report:EndpointBenchmarkReport}>
     */
    private function profileEntities(EndpointProfiler $profiler, string $module, array $entities, int $iterations, int $warmup, Authenticatable $user): array
    {
        $reports = [];

        foreach ($entities as $entity) {
            $uri = sprintf('/api/v1/select/%s/%s', $module, $entity);
            $reports[] = [
                'entity' => $entity,
                'report' => $profiler->profile('GET', $uri, $iterations, $warmup, $user),
            ];
        }

        return $reports;
    }

    /**
     * @param  list<array{entity:string,report:EndpointBenchmarkReport}>  $reports
     */
    private function renderTable(string $module, array $reports): void
    {
        $rows = [];

        foreach ($reports as $entry) {
            $report = $entry['report'];
            $stats = $report->benchmark->durationStats;
            $rows[] = [
                sprintf('%s/%s', $module, $entry['entity']),
                (string) $report->lastStatus,
                sprintf('%.2f', $stats->p50),
                sprintf('%.2f', $stats->p95),
                sprintf('%.2f', $stats->max),
                sprintf('%.1f', $report->benchmark->queryStats->mean),
                sprintf('%.1f', $report->benchmark->peakMemoryBytes / 1048576),
            ];
        }

        $this->table(
            ['entity', 'status', 'p50 (ms)', 'p95 (ms)', 'max (ms)', 'queries', 'peak MB'],
            $rows,
        );

        if ($this->anyNonSuccess($reports)) {
            $this->warn('Some endpoints did not return 200 — check that the entity is exposed and the user is authorized.');
        }
    }

    /**
     * @param  list<array{entity:string,report:EndpointBenchmarkReport}>  $reports
     */
    private function anyNonSuccess(array $reports): bool
    {
        foreach ($reports as $entry) {
            if ($entry['report']->lastStatus !== 200) {
                return true;
            }
        }

        return false;
    }

    private function resolveUser(AuthFactory $auth, string $userId): ?Authenticatable
    {
        $guard = $auth->guard();

        if (! method_exists($guard, 'getProvider')) {
            return null;
        }

        return $guard->getProvider()->retrieveById($userId);
    }
}
