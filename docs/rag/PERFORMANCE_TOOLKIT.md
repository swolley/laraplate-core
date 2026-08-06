# Performance toolkit — developer guide

A set of dev-only Artisan commands to make performance **observable** with real,
reproducible numbers, without adding any runtime dependency. Use them to
establish a baseline before optimizing and to prove before/after on any change.

> Measure first, cut second. A number you did not measure is a guess.

## Components

```text
BenchmarkStats      # nearest-rank percentiles over samples (pure)
BenchmarkRunner     # runs an operation N times (+warmup), timing + query count
EndpointProfiler    # dispatches real requests through the HTTP kernel
CachegrindParser    # ranks functions by self-cost from an Xdebug profile
SubprocessBootSampler / BootSampler  # cold-boot samples via fresh processes
```

All live in `Modules\Core\Performance` (+ the `BootSampler` contract in
`Modules\Core\Contracts`). They are reusable directly from tests or tinker.

## Commands

### `perf:bench` — endpoint latency

Profiles one or more endpoints through the **real kernel** (full
middleware/routing/controller/authorization/serialization stack), reporting
p50/p95/max latency, per-request query count and peak memory.

```bash
php artisan perf:bench GET:/app/about "GET:/api/v1/health" --iterations=50 --warmup=5
php artisan perf:bench "GET:/app/user/profile-information" --user=1   # act as a user
php artisan perf:bench GET:/app/about --json                          # machine-readable
```

`--user=<id>` authenticates on the guard (so `$request->user()` / `auth()->user()`
see the user). It does not bypass hard `auth:sanctum` middleware.

### `perf:crud` — the CRUD engine across entities

Enables the public CRUD API for the process and profiles the `/api/v1/select`
list endpoint per entity. Unless `--user` is given, it creates a superadmin
inside a transaction that is **always rolled back** — the run leaves no data.

```bash
php artisan perf:crud --module=core --entity=users --entity=roles --iterations=20
php artisan perf:crud --module=cms --entity=contents --user=1   # existing user, no transaction
```

Reads only; if any endpoint is not `200` a warning is printed (entity not
exposed, or the user is not authorized).

### `perf:profile` — Xdebug flame profile

Produces (or summarizes) an Xdebug profile and ranks the hottest functions by
**self cost**, so bottlenecks are pinpointed rather than guessed. Framework/vendor
noise (autoload, PDO connect, tinker) is filtered unless `--all`.

```bash
php artisan perf:profile "GET:/app/about" --count=20 --limit=25   # spawns a profiled child
php artisan perf:profile --file=cachegrind.out.12345              # summarize an existing profile
php artisan perf:profile "GET:/app/about" --all                   # include vendor/autoload noise
```

The spawn mode requires the Xdebug extension. The `--file` mode does not, and
works on any cachegrind — including one captured from FPM in a production-like
run (`XDEBUG_PROFILE`).

### `perf:boot` — framework boot time

Samples cold boot across many fresh sub-processes and reports percentiles,
amortizing the per-run noise that makes a single CLI boot measurement
untrustworthy.

```bash
php artisan perf:boot --runs=30
```

## Interpreting results — what the baseline showed

On the dev box (no OPcache, debug on), the CRUD list engine returned in
~50–110 ms for **1–3 queries**: the database is not the bottleneck — the cost is
the **PHP request pipeline** (request parsing → query building with
reflection/schema inspection → serialization), plus per-request cache (Redis)
round-trips. Much of a dev boot is class autoloading, which OPcache eliminates in
production; the residual production cost is container resolution, routing,
validation and entity resolution.

Two consequences when reading numbers:

- **Dev is a worst case.** Absolute ms are inflated by no-OPcache compilation and
  debug mode. Trust *relative* deltas and *query counts* (which transfer across
  environments) more than absolute dev ms.
- **Xdebug inflates absolute time** but preserves the self-cost *distribution*;
  read `perf:profile` output as proportions, not milliseconds.

## Notes

- All commands are dev/diagnostic tools; they add no runtime dependency and are
  safe to run against a local database (`perf:crud` rolls back its own writes).
- `perf:profile` and `perf:boot` spawn child processes using the current
  environment (`.env`), not the test environment.
