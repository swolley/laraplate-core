<?php

declare(strict_types=1);

namespace Modules\Core\Seeding;

use Illuminate\Console\Command;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Core\Services\SettingsCacheCoordinator;
use Throwable;

/**
 * Runs the seed graph in dependency order, stopping immediately on the first
 * failure and recording per-node progress so a re-run can resume instead of
 * repeating already-completed nodes.
 *
 * Node-level transaction scope: this class wraps each node in a transaction on
 * the *default* connection (resolved via the injected {@see DatabaseManager},
 * never the `DB` facade). That is a deliberate, documented compromise, not an
 * oversight: {@see SeedNode} carries no connection information, and a seeder is
 * free to write to any number of models on any number of connections, so there
 * is no general way for the orchestrator to know which connection(s) a given
 * node actually touches without guessing at its internals. Wrapping the
 * *default* connection catches the common case — most seeders in this codebase
 * only ever write default-connection models — while nodes that write elsewhere
 * remain responsible for wrapping those specific writes in their own
 * transaction derived from that connection, exactly as {@see
 * \Modules\Core\Overrides\Seeder::seedSettingDefinitions()} already does and as
 * the project's connection-affinity architecture tests already require of every
 * production seeder (no `DB::transaction(` inside a seeder file). This class
 * does not change or relax that requirement; it adds a backstop for the
 * default connection on top of it.
 */
final class SeedOrchestrator
{
    /** @var list<SeedNode>|null */
    private ?array $nodes = null;

    private ?Command $command = null;

    public function __construct(
        private readonly SeedGraphBuilder $builder,
        private readonly SeedLedger $ledger,
        private readonly SettingsCacheCoordinator $cache,
        private readonly DatabaseManager $connections,
    ) {}

    /**
     * Override graph discovery. Tests use this; production always discovers.
     *
     * @param  list<SeedNode>  $nodes
     */
    public function withNodes(array $nodes): self
    {
        $this->nodes = SeedGraph::sort($nodes);

        return $this;
    }

    /**
     * Propagate the invoking console command, if any, to every resolved node.
     *
     * Mirrors {@see Seeder::resolve()}: without this, seeders instantiated
     * directly through the container (as this class must, to run nodes the
     * graph discovers rather than ones a parent seeder calls) would have a
     * null `$this->command`, and several production seeders
     * (CoreDatabaseSeeder, CMSDatabaseSeeder, ...) call `$this->command->line()`
     * without a null-safe operator and would fatal.
     */
    public function withCommand(?Command $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Execute every node in dependency order.
     *
     * Returns 0 on success, 1 on the first failure. Nodes after a failure do
     * not run: a release must stop rather than apply half a configuration.
     */
    public function run(?string $resumeRunId = null): int
    {
        $nodes = $this->nodes ?? $this->builder->build();
        $run_id = $resumeRunId ?? (string) Str::uuid();
        $already_done = $resumeRunId === null
            ? []
            : array_flip($this->ledger->completedNodes($resumeRunId));

        foreach ($nodes as $node) {
            if (isset($already_done[$node->seederClass])) {
                continue;
            }

            $this->ledger->start($run_id, $node->seederClass);

            try {
                $this->runNode($node->seederClass);

                // Placeholder: hashes only the class name, not what the node would
                // write. No flag in this command consumes it for skip decisions
                // (--skip-unchanged is deliberately not registered) — a hash that
                // faithfully represents a node's output requires extracting its
                // definitions without executing it, which nothing here delivers.
                $this->ledger->succeed(
                    $run_id,
                    $node->seederClass,
                    hash('xxh128', $node->seederClass),
                );
            } catch (Throwable $throwable) {
                $this->ledger->fail($run_id, $node->seederClass, $throwable->getMessage());
                $this->reportFailure($run_id, $node->seederClass, $throwable);

                return 1;
            }
        }

        $this->cache->flushAll();

        return 0;
    }

    /**
     * Resolve and run one node's seeder inside a default-connection transaction.
     *
     * @param  class-string  $seederClass
     */
    private function runNode(string $seederClass): void
    {
        /** @var Seeder $seeder */
        $seeder = app($seederClass);
        $seeder->setContainer(app());

        if ($this->command instanceof Command) {
            $seeder->setCommand($this->command);
        }

        $this->connections->connection()->transaction(static function () use ($seeder): void {
            // Invoke through __invoke(), not run() directly, so container
            // method-injection and the WithoutModelEvents trait (if a seeder
            // uses it) behave exactly as they do under Seeder::call().
            $seeder();
        });
    }

    /**
     * @param  class-string  $seederClass
     */
    private function reportFailure(string $runId, string $seederClass, Throwable $throwable): void
    {
        $message = "Seed node failed: {$seederClass}\n{$throwable->getMessage()}";

        Log::error('Seed node failed', [
            'run_id' => $runId,
            'node' => $seederClass,
            'exception' => $throwable,
        ]);

        if ($this->command instanceof Command) {
            $this->command->error($message);

            return;
        }

        if (App::runningInConsole()) {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }
}
