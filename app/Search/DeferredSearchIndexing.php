<?php

declare(strict_types=1);

namespace Modules\Core\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Laravel\Scout\ModelObserver;
use Modules\Core\Contracts\ISearchableModel;
use Modules\Core\Search\Exceptions\DeferredRunInterruptedException;
use Throwable;

/**
 * Defers search indexing for the duration of a bulk operation such as an
 * import. Every `searchable()` call made while {@see run()} is active is
 * recorded as a (model class, scout key) pair instead of being indexed one
 * model at a time. The recorded models are indexed through the bulk path, one
 * batched pre-process pass (embeddings) and adaptive engine writes, every
 * `batchSize` distinct models and once at the end. Only keys are held in
 * memory: the models are reloaded when flushed, and rows deleted in the
 * meantime are skipped.
 *
 * A threshold flush never runs inside an open database transaction: it waits
 * for the commit, and a rollback leaves the keys pending for the next flush.
 *
 * A run can be stopped with {@see interrupt()}: it unwinds by exception, right
 * away or once the flush in progress completes, and indexes what it recorded.
 */
final class DeferredSearchIndexing
{
    private bool $active = false;

    private bool $discard = false;

    private bool $flushing = false;

    private bool $interruptAfterFlush = false;

    private bool $listening = false;

    /**
     * @var int<1, max>
     */
    private int $batchSize = 1;

    /**
     * Distinct pending scout keys, grouped by model class.
     *
     * @var array<class-string<Model&ISearchableModel>, array<int|string, true>>
     */
    private array $pending = [];

    private int $pendingCount = 0;

    /**
     * Run the callback with indexing deferred, then flush what it left pending.
     * In discard mode the captured calls are dropped: nothing is indexed and
     * nothing is embedded. A nested call joins the outer run.
     *
     * @template TResult
     *
     * @param  callable(): TResult  $callback
     * @return TResult
     */
    public function run(callable $callback, int $batchSize, bool $discard = false): mixed
    {
        if ($this->active) {
            return $callback();
        }

        $this->active = true;
        $this->discard = $discard;
        $this->batchSize = max(1, $batchSize);
        $this->listenForSaves();

        try {
            $result = $callback();
            $this->flush();

            return $result;
        } catch (Throwable $exception) {
            // Graphs committed before the failure are real rows: index them,
            // without letting a flush error hide the one that stopped the run.
            $this->flushQuietly();

            throw $exception;
        } finally {
            $this->reset();
        }
    }

    public function isDeferring(): bool
    {
        return $this->active && ! $this->flushing;
    }

    public function isFlushing(): bool
    {
        return $this->flushing;
    }

    /**
     * Distinct models recorded and not flushed yet.
     */
    public function pendingCount(): int
    {
        return $this->pendingCount;
    }

    /**
     * Whether stopping now would leave recorded models unindexed.
     */
    public function hasPendingWork(): bool
    {
        return $this->pendingCount > 0 || $this->flushing;
    }

    /**
     * Stop the run: throw {@see DeferredRunInterruptedException} now, or right
     * after the flush in progress, so the run indexes what it recorded while it
     * unwinds. A transaction open at that moment rolls back with the unwinding.
     *
     * @throws DeferredRunInterruptedException
     */
    public function interrupt(): void
    {
        if ($this->flushing) {
            $this->interruptAfterFlush = true;

            return;
        }

        throw new DeferredRunInterruptedException('The deferred search indexing run was interrupted.');
    }

    /**
     * Take over a `searchable()` call. Returns false when nothing is being
     * deferred, so the caller indexes the models as usual.
     *
     * @param  iterable<Model&ISearchableModel>  $models
     */
    public function defer(iterable $models): bool
    {
        if (! $this->isDeferring()) {
            return false;
        }

        if ($this->discard) {
            return true;
        }

        $last = null;

        foreach ($models as $model) {
            $key = $model->getScoutKey();

            if (! isset($this->pending[$model::class][$key])) {
                $this->pending[$model::class][$key] = true;
                $this->pendingCount++;
            }

            $last = $model;
        }

        if ($last !== null && $this->pendingCount >= $this->batchSize) {
            // Runs at once outside a transaction and after the commit inside one.
            $last->getConnection()->afterCommit(fn () => $this->flushWhenFull());
        }

        return true;
    }

    /**
     * Index every recorded model now, in chunks of the batch size per class.
     */
    public function flush(): void
    {
        if ($this->flushing || $this->pending === []) {
            return;
        }

        $pending = $this->pending;
        $this->pending = [];
        $this->pendingCount = 0;
        $this->flushing = true;

        try {
            foreach ($pending as $class => $keys) {
                foreach (array_chunk(array_keys($keys), $this->batchSize) as $chunk) {
                    $this->indexChunk($class, $chunk);
                }
            }
        } finally {
            $this->flushing = false;
        }

        if ($this->interruptAfterFlush) {
            $this->interruptAfterFlush = false;

            throw new DeferredRunInterruptedException('The deferred search indexing run was interrupted.');
        }
    }

    /**
     * Record every searchable model saved inside a transaction as soon as it is
     * saved, rather than only when Scout's after-commit observer reaches it: a
     * run interrupted in the middle of a commit's after-commit callbacks then
     * loses none of the models that commit wrote. A key whose transaction rolls
     * back is harmless, the flush reloads by key and skips missing rows.
     * Outside a transaction the observer already runs at save time.
     */
    private function listenForSaves(): void
    {
        if ($this->listening) {
            return;
        }

        $this->listening = true;

        Event::listen('eloquent.saved: *', function (string $event, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || ! $model instanceof ISearchableModel || ModelObserver::syncingDisabledFor($model)) {
                return;
            }

            if (app('db.transactions')->callbackApplicableTransactions()->isNotEmpty()) {
                $this->defer([$model]);
            }
        });
    }

    private function flushWhenFull(): void
    {
        if ($this->pendingCount >= $this->batchSize) {
            $this->flush();
        }
    }

    private function flushQuietly(): void
    {
        try {
            $this->flush();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Reload one chunk through Scout's import query (the same scopes
     * `scout:import` drops) and index it through the bulk path.
     *
     * @param  class-string<Model&ISearchableModel>  $class
     * @param  list<int|string>  $keys
     */
    private function indexChunk(string $class, array $keys): void
    {
        $instance = new $class;

        $models = $class::makeAllSearchableQuery()
            ->whereIn($instance->qualifyColumn($instance->getScoutKeyName()), $keys)
            ->get()
            ->filter(static fn (ISearchableModel $model): bool => $model->shouldBeSearchable())
            ->values();

        if ($models->isEmpty()) {
            return;
        }

        $instance->makeSearchableInBulk($models);
    }

    private function reset(): void
    {
        $this->active = false;
        $this->discard = false;
        $this->interruptAfterFlush = false;
        $this->batchSize = 1;
        $this->pending = [];
        $this->pendingCount = 0;
    }
}
