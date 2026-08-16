<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true" width="400" alt="Laravel Logo" />
    </a>
</p>
<p>&nbsp;</p>

> ⚠️ **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided... or better yet, collaborate with me to create the definitive Laravel boilerplate; that's the right place to instroduce your ideas. Let me know your ideas...

## Table of Contents

-   [Description](#description)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Features](#features)
-   [Scripts](#scripts)
-   [Contributing](#contributing)
-   [License](#license)

## Description

The Core Module contains all the necessary functionalities to build a new Laravel application.

## Documentation

-   [Graph system](docs/GRAPH_SYSTEM.md): CRUD-aligned graph expand/search/stats framework for every CRUD-resolvable entity.
-   [Event orchestration](docs/EVENT_ORCHESTRATION.md): cross-module indexing and moderation events.
-   Transactional outbox: durable cross-system integration events with an application-replaceable publisher and queued delivery.
-   [ACL system](docs/ACL_SYSTEM.md): permission and row-level access control behavior.
-   [CRUD system](docs/CRUD_SYSTEM.md): generic CRUD pipeline and API behavior.
-   [Module import framework](docs/IMPORT_FRAMEWORK.md): abstract command and neutral services for module-owned external importers.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

Add repository to your `composer.json` file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core
```

Then, you can install the module by running the following command:

```bash
php artisan module:install Core
```

## Configuration

```env
#users
VERIFY_NEW_USER=true							#enables email verification for new users
ENABLE_USER_REGISTRATION=true					#enables user registration
ENABLE_SOCIAL_LOGIN=true						#enables social login
ENABLE_USER_LICENSE=false						#enables user licenses
ENABLE_USER_2FA=false							#enables two-factor authentication
AUTH_MODEL=App\Models\User				#authentication model

#locking
LOCKIN_LOCK_VERSION_COLUMN=lock_version			#column name for the lock version
LOCKIN_LOCK_AT_COLUMN=locked_at					#column name for the lock date
LOCKIN_LOCK_BY_COLUMN=locked_user_id			#column name for the lock user id
LOCKIN_UNLOCK_ALLOWED=true						#enables unlock of locked objects
LOCKING_CAN_BE_UNLOCKED=						#comma separated list of user ids that can unlock locked objects
LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED=false	#prevents modifications on locked objects
LOCKING_PREVENT_MODIFICATIONS_TO_LOCKED=false	#prevents modifications to locked objects

#entities
ENABLE_DYNAMIC_ENTITIES=false					#enables dynamic entities
ENABLE_DYNAMIC_GRIDUTILS=false					#enables dynamic gridutils
EXPOSE_CRUD_API=false							#enables CRUD API

#https
FORCE_HTTPS=false								#enables HTTPS

#logging
GRAYLOG_LEVEL=error								#log level for graylog
GRAYLOG_URL=									#graylog url
GRAYLOG_PORT=12201								#graylog port

#permission
PERMISSION_MODEL=Modules\Core\Models\Permission	#permission model
ROLE_MODEL=Modules\Core\Models\Role				#role model
SUPERADMIN_ROLE=superadmin						#superadmin role name
ADMIN_ROLE=admin								#admin role name
GUEST_ROLE=guest								#guest role name
SUPERADMIN_USER=superadmin						#superadmin user name
ADMIN_USER=admin								#admin user name
GUEST_USER=anonymous							#guest user name

#queues
HORIZON_DOMAIN=									#horizon domain
HORIZON_PATH=									#horizon path
HORIZON_PREFIX=									#horizon prefix

#ai (deprecated - moved to AI module)
# AI configuration is now in the AI module. See Modules/AI/README.md for configuration.
# These variables are kept for backward compatibility but will be removed in future versions.
OPENAI_API_KEY=									#openai api key (deprecated - use AI module config)
OPENAI_API_URL=                                 #openai compatible api url (deprecated - use AI module config)
OPENAI_MODEL=                                   #openai model (deprecated - use AI module config)
OLLAMA_API_URL=                                 #ollama compatible api url (deprecated - use AI module config)
OLLAMA_MODEL="llama3.2:3b"						#ollama model (deprecated - use AI module config)

#search
SCOUT_DRIVER=typesense                          #actually supperted drivers with full functionalities (typesense, elasticsearch)
VECTOR_SEARCH_ENABLED=true                      #create embeddings with ai functionalities before indexing in search engine
EMBEDDING_PROVIDER=openai                       #actually supported embedding generator provider (openai, ollama)
SEARCH_ENGINE=elasticsearch						#default search engine
SEARCH_DATABASE_PG_TRGM_ENABLED=false             #enable PostgreSQL pg_trgm matching for the database Scout driver
VECTOR_DIMENSION=768							#vector dimension for embeddings
VECTOR_SIMILARITY=cosine						#vector similarity metric (cosine, dot_product, euclidean)
VECTOR_DIMENSIONS=1536							#vector dimensions for OpenAI default
SCOUT_PREFIX=									#scout index prefix
SCOUT_QUEUE=true								#enable scout queue
SCOUT_QUEUE_NAME=indexing						#scout queue name
SCOUT_QUEUE_TRIES=3								#scout queue retry attempts
SCOUT_QUEUE_TIMEOUT=120							#scout queue timeout
SCOUT_QUEUE_BACKOFF=30,60,120					#scout queue backoff times
SCOUT_IDENTIFY=false							#identify user in search engine

#elasticsearch
ELASTIC_INDEX_PREFIX=						#elasticsearch index prefix
ELASTIC_QUEUE_CONNECTION=sync				#elasticsearch queue connection
ELASTIC_QUEUE=indexing					#elasticsearch queue name
ELASTIC_QUEUE_TIMEOUT=300					#elasticsearch queue timeout
ELASTIC_QUEUE_TRIES=3						#elasticsearch queue retry attempts
ELASTIC_CONNECTION=default						#elasticsearch connection name
ELASTIC_HOST=localhost:9200						#elasticsearch host
ELASTIC_RETRIES=3								#elasticsearch retry attempts
ELASTIC_TIMEOUT=60								#elasticsearch timeout
ELASTIC_CONNECT_TIMEOUT=10						#elasticsearch connection timeout
ELASTIC_SSL_VERIFICATION=true					#elasticsearch SSL verification
ELASTIC_USERNAME=								#elasticsearch username
ELASTIC_PASSWORD=								#elasticsearch password
ELASTIC_LOG_ENABLED=false						#elasticsearch logging enabled
ELASTIC_LOG_LEVEL=error							#elasticsearch log level
ELASTIC_RETRY_ON_CONFLICT=3						#elasticsearch retry on conflict
ELASTIC_BULK_SIZE=500							#elasticsearch bulk size
ELASTIC_SCOUT_DRIVER_REFRESH_DOCUMENTS=false	#elasticsearch scout driver refresh documents

#typesense
TYPESENSE_API_KEY=xyz							#typesense api key
TYPESENSE_HOST=localhost						#typesense host
TYPESENSE_PORT=8108								#typesense port
TYPESENSE_PATH=									#typesense path
TYPESENSE_PROTOCOL=http							#typesense protocol
TYPESENSE_CONNECTION_TIMEOUT_SECONDS=2			#typesense connection timeout
TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS=30		#typesense healthcheck interval
TYPESENSE_NUM_RETRIES=3							#typesense number of retries
TYPESENSE_RETRY_INTERVAL_SECONDS=1				#typesense retry interval
TYPESENSE_INDEX_PREFIX=							#typesense index prefix
TYPESENSE_HOSTS=http://localhost:8108			#typesense hosts (comma separated)

#cache
CACHE_DURATION_SHORT=10							#short cache duration in seconds
CACHE_DURATION_MEDIUM=300						#medium cache duration in seconds
CACHE_DURATION_LONG=3600						#long cache duration in seconds

#app
APP_LOGO=										#application logo
SOFT_DELETES_EXPIRATION_DAYS=					#soft deletes expiration days

#social login
FACEBOOK_CLIENT_ID=								#facebook client id
FACEBOOK_CLIENT_SECRET=							#facebook client secret
X_CLIENT_ID=									#x client id
X_CLIENT_SECRET=								#x client secret
LINKEDIN_OPENID_CLIENT_ID=						#linkedin openid client id
LINKEDIN_OPENID_CLIENT_SECRET=					#linkedin openid client secret
GOOGLE_CLIENT_ID=								#google client id
GOOGLE_CLIENT_SECRET=							#google client secret
GITHUB_CLIENT_ID=								#github client id
GITHUB_CLIENT_SECRET=							#github client secret
GITLAB_CLIENT_ID=								#gitlab client id
GITLAB_CLIENT_SECRET=							#gitlab client secret
BITBUCKET_CLIENT_ID=							#bitbucket client id
BITBUCKET_CLIENT_SECRET=						#bitbucket client secret
SLACK_CLIENT_ID=								#slack client id
SLACK_CLIENT_SECRET=							#slack client secret
SLACK_OPENID_CLIENT_ID= 						#slack openid client id
SLACK_OPENID_CLIENT_SECRET=						#slack openid client secret
SOCIALITE_REDIRECT= 							#socialite redirect
```

### Versioning configuration

`version_strategy_{table}` settings are runtime controls only for models that do not pin a strategy in code. When a model declares `versionStrategy = VersionStrategy::DIFF`, `ForcedVersionStrategySettings` excludes any historical matching row from the Settings resource and prevents recreating it through that form. The row is not deleted automatically.

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasVersions;

class ... extends Model
{
    use HasVersions;

	protected $versionable = [ /** versionable fields */ ];
	protected $dontVersionable = [ /** fields not versionable */ ];
}
```

### Advanced search filters

Core search applies filters inside the active engine before pagination. When a model exposes a searchable schema, public filters are accepted only on fields declared filterable or facetable.

Indexed relation fields use schema-declared dot paths such as `tags.id`. Elasticsearch receives nested queries, Typesense receives nested-field filter syntax, and the database driver uses the schema field option `relation` to translate the same filter to `whereHas` / `whereDoesntHave`. On relation fields, `!=` and `not in` mean anti-exists: no related indexed row may match the value/list.

### Portable text matching

Text matching is configured under `search.text_matching.defaults` and translated by each engine adapter. The options are granular so named profiles can remain replaceable presets instead of becoming part of the engine contract.

The database engine always provides case-insensitive prefix or substring matching. PostgreSQL can additionally use `strict_word_similarity()` after installing the trusted `pg_trgm` extension and setting `SEARCH_DATABASE_PG_TRGM_ENABLED=true`. Other database drivers report typo tolerance as degraded. Oracle intentionally uses the portable fallback: `UTL_MATCH` is not index-backed for generic retrieval over long text, while Oracle Text requires explicit `CONTEXT` indexes and a schema-aware adapter.

### Elasticsearch configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Search\Traits\Searchable;

class ... extends Model
{
    use Searchable {
        toSearchableArray as toSearchableArrayTrait;
    }

	protected function toSearchableArray(): array
    {
		// get default model data if you like
		$document = $this->toSearchableArrayTrait();
        // add your customizations
		return $document + [
            'id' => 'keyword',
            'title' => 'text',
            'published' => 'boolean',
            'created_at' => 'date',
			// ...
        ];
    }
}
```

### Validations configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasValidations;

class ... extends Model
{
    use HasValidations;

	protected $rules = [
		'create' => [ /** rules for create */ ],
		'update' => [ /** rules for update */ ],
		'always' => [ /** rules for always */ ],
	];
}
```

### Validity configuration

`HasValidity` adds `valid_from` / `valid_to` datetime columns (via `MigrateUtils::timestamps(..., hasValidity: true)`) and helpers such as `isValid()`, `isExpired()`, `isScheduled()`, `isDraft()`, `publish()`, and `unpublish()`.

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasValidity;

class ... extends Model
{
    use HasValidity;

	protected static $valid_from_column = 'valid_from';
	protected static $valid_to_column = 'valid_to';
}
```

#### Temporary users (`Core\Models\User`)

`User` uses `HasValidity` so accounts can expire automatically at `valid_to` without soft-delete or manual deactivation.

| Account type | `valid_from` | `valid_to` |
| --- | --- | --- |
| Permanent | `now()` (or start date) | `null` |
| Temporary | access start | access end |

```php
// Seven-day contractor account
$user->publish(now(), now()->addDays(7));

// Or direct assignment
$user->update([
    'valid_from' => now(),
    'valid_to' => now()->addDays(7),
]);
```

Seeded system users (`superadmin`, `admin`, `guest`, `system`) use `valid_from => now()` and `valid_to => null`. Enforce the window at login by checking `$user->isValid()` in Fortify/Socialite providers or auth middleware (not applied automatically on every query).

If you need to override the Core Module or dependencies configs you can publish them by running the following command:

## PHP Attributes

This module uses PHP 8 attributes to improve type safety and follow modern PHP standards.

### Override Attribute

Use `#[Override]` when overriding parent methods for better type safety:

```php
use Override;

#[Override]
public function getAttribute($key): mixed
{
    // Your implementation
}
```


## Seeding and reconciliation

Production seeders run on **every release**, against a database that already has real operator
data in it. `Modules\Core\Seeding` (`Modules/Core/app/Seeding/`) is the mechanism that makes that
safe: it orders seeders by declared dependency instead of a flat priority number, reconciles rows
without clobbering values an operator changed after seeding, and removes settings whose owning
module is gone — never anything else.

### Dependency graph replaces `module.json` priority

A seeder opts into explicit ordering by implementing `DeclaresSeedDependencies`:

```php
<?php
namespace Modules\ERP\Database\Seeders;

use Modules\Core\Seeding\Contracts\DeclaresSeedDependencies;

final class ErpDatabaseSeeder extends Seeder implements DeclaresSeedDependencies
{
    public static function dependsOn(): array
    {
        return [\Modules\Core\Database\Seeders\CoreDatabaseSeeder::class];
    }
}
```

`dependsOn()` is only for edges *within the same module* or edges that are not already implied by
`module.json`. `SeedGraphBuilder` adds an implicit edge from every seeder of a module to every
seeder of each module it `requires` in `module.json`, so cross-module order does not need to be
restated. `SeedGraph::sort()` then performs a topological sort with a deterministic
`[module, seederClass]` tie-break, so the same set of seeders always produces the same order
regardless of discovery order.

This replaced an implicit `module.json` `priority` ordering that could silently violate a
module's own declared `requires`: `MES` declares `"requires": ["ERP"]` but carries a lower
`priority` (`2`) than `ERP` (`99`), so under priority-based ordering `MES` seeded before the `ERP`
data it depends on. The graph raises instead of degrading silently: `SeedGraphCycleException` on a
dependency cycle, `MissingSeedDependencyException` when a declared dependency belongs to a module
that is disabled or absent (a seeder cannot depend on a class the graph never discovered).

Seeder discovery is automatic: any `database/seeders/*.php` class of an enabled module is a node,
except classes whose basename starts with `Dev` (`DevDatabaseSeeder` and friends are never part of
the production graph). A seeder does not need to implement `DeclaresSeedDependencies` at all if
`module.json requires` already expresses everything it needs — `CoreDatabaseSeeder` is the
example: it declares no dependencies of its own.

### Declaring what to reconcile — `SeedDefinition`

`SeedDefinition` is a value object that tells `SeedReconciler` how to treat a set of rows for one
model:

```php
$outcome = app(SeedReconciler::class)->reconcile(
    SeedDefinition::for(Setting::class)
        ->identity(['name'])
        ->structural(['type', 'group_name', 'description', 'choices'])
        ->initial(['value'])
        ->ownedBy('Core')
        ->rows($defaultSettings),
);
```

- **`identity`** must be exactly one column. A composite identity would need one `OR` clause per
  row, which defeats the fixed query budget the reconciler exists to provide;
  `identityColumn()` throws `LogicException` unless exactly one column is declared.
- **`structural`** fields follow the code: every release realigns them to whatever the seeder
  declares, overwriting any value an operator set directly.
- **`initial`** fields are written once, at creation, and never touched again by a later run —
  this is how a seeded default (e.g. a `Setting`'s `value`) stays an operator's to change.
- **`rows()`** normalizes every empty string (`''`) to `null` and throws `InvalidArgumentException`
  if a row is missing the identity column or its identity value is null/empty/absent. Do this
  normalization here, not in a model saving hook: reconciliation writes via `upsert()`, which never
  fires Eloquent events.

### `SeedReconciler` and drift

`SeedReconciler::reconcile()` runs a fixed number of queries regardless of row count: one read
(existing rows keyed by identity), one `upsert()` for created/realigned rows, one `restore()` for
soft-deleted rows found among the declared set, and one baseline-backfill `upsert()` for rows that
predate the `seeded_value` column — each write only runs when its row set is non-empty. Because it
writes through `upsert()`, **no Eloquent event fires** (no observers, no `saving`/`saved`) — this is
deliberate, not an oversight, and is why `SeedDefinition::rows()` does its own normalization instead
of relying on a model hook. The `$update` list passed to the realignment `upsert()` is exactly
`$definition->structural` — nothing else. `value` and `seeded_value` must never be added to it: doing
so would silently overwrite an operator's customization on every release.

For `Setting`, drift is **computed**, never asserted: `core_settings.module` records the owning
module (`null` when the row was not written by a seeder) and `core_settings.seeded_value` records
the last value a seeder wrote there. A setting has drifted when `value !== seeded_value`, compared
via `ValueComparator::equal()` (which normalizes JSON key order, numeric-string vs. numeric, and
`BackedEnum` vs. its backing value so none of those read as drift). `seeded_value IS NULL` means the
seeder never wrote that row — for example, a row created before this column existed.

### Cleanup by module state

`SettingsCleaner::clean()` runs once, after every node in the graph has succeeded, and only ever
touches rows a seeder actually wrote:

| `module` | `seeded_value` | Module state | Drifted (`value !== seeded_value`) | Outcome |
| --- | --- | --- | --- | --- |
| `null` | any | — | — | never a candidate, always preserved |
| any | `null` | — | — | never a candidate, always preserved |
| set | set | Enabled | any | preserved |
| set | set | Disabled | No | hard deleted (`forceDelete()`) |
| set | set | Disabled | Yes | soft deleted (`delete()`) — operator's customization kept, recoverable |
| set | set | Absent (module directory gone) | any | hard deleted (`forceDelete()`) |

The `whereNotNull('module')->whereNotNull('seeded_value')` pair on the candidate query is the
safety mechanism, not an optimization: a row without both is never a seeder-owned row and must
never be a deletion candidate. Keep that filter in the query, not in the loop body, so a later
refactor of the loop cannot bypass it.

Module state itself is resolved by `ModuleStateResolver`, which uses `Module::find($name)?->isEnabled()`
rather than an array lookup — see [Gotchas](#gotchas-for-seeder-and-test-authors) below.

### Resuming a failed run

`SeedOrchestrator::run()` executes every node in graph order inside its own transaction, stops at
the **first** failure (a release must not apply half a configuration), and records progress per
node in the `core_seed_runs` ledger (`SeedLedger`, table `CoreTables::SeedRuns`). `db:seed --resume`
(declared by `Modules\Core\Console\SeedCommand`) re-runs the last failed `run_id` and skips any node
already marked `succeeded` under it, so a fix-and-retry does not repeat completed work.

`--skip-unchanged` is deliberately **not implemented**. The ledger already records a `content_hash`
per succeeded node, but today that hash is a placeholder — `hash('xxh128', $node->seederClass)`,
the class name only, not a digest of what the node would actually write. A `--skip-unchanged` flag
built on that hash would skip nodes whose content changed but whose class name did not, which is
the opposite of what the flag would promise. Do not wire a skip decision to `content_hash` until it
hashes the node's actual definitions.

### Dev data and volume scaling

`db:seed --dev` (declared by `Modules\Core\Console\SeedCommand`) runs the `Dev*` seeders instead of
the production graph: it dispatches `DevDatabaseSeeder`, which discovers every enabled module's
`database/seeders/Dev*.php` class and fills the database with fake bulk data. These seeders extend
`Modules\Core\Helpers\BatchSeeder` and pass a hardcoded target count (their "max" size) to
`createInBatches()` / `createInParallelBatches()`.

Four mutually-exclusive flags scale that fake volume without editing the seeders:

| Flag | Multiplier | Example (`TARGET_COUNT_CONTENTS = 500_000`) |
| --- | --- | --- |
| `--micro` | ×0.01 | 5 000 |
| `--min` | ×0.1 | 50 000 |
| `--mid` | ×0.5 | 250 000 |
| `--max` | ×1.0 | 500 000 |

- The default, when no flag is given, is `--micro` (1% — a fast, workable dev dataset).
- Precedence is fixed as `micro → min → mid → max`: the first one present wins (so `--min --mid`
  resolves to `--min`), regardless of the order typed on the command line.
- `SeedCommand` resolves the factor **once**, from the real invocation flags, and publishes it on the
  container under `BatchSeeder::SCALE_CONTAINER_KEY` before dispatching (clearing it in a `finally`).
  `BatchSeeder` reads the factor from there and multiplies each target count. It is **not** read off
  `$this->command`: `db:seed` is a shared container instance whose input is rebound by the nested
  `module:seed → db:seed` calls the dev seeders make, so a seeder reading `option('min')` later would
  see that mutated input, not the operator's flags. A positive target is floored to at least one
  record, so a scaled-down run never leaves a table empty for a dependent seeder; a zero target
  stays zero.
- Only fake bulk volume scales. Reference/settings data seeded via `module:seed` inside the dev
  seeders is **not** scaled — partial reference data would break dependencies.
- The flags are no-ops outside `--dev`: the production graph seeds fixed reference data only, and the
  container key is never published.

The same flags work per module: `module:seed <Module> --dev [--min|--mid|--max]`
(`Modules\Core\Console\ModuleSeedCommand`, an override of nwidart's `module:seed`) runs that module's
`Dev{Module}DatabaseSeeder` alone, publishing the scale the same way. Both commands share
`Modules\Core\Console\Concerns\ResolvesDevSeedScale` so the flag set, precedence, and container
publishing live in one place.

### Per-node atomicity — what "rolled back" does and does not mean

Each node runs inside a transaction on the **default** database connection only, opened via the
injected `DatabaseManager`. This is a documented compromise: `SeedNode` carries no connection
information, and a seeder is free to write to any model on any connection, so the orchestrator has
no general way to know which connection(s) a given node touches. If a node fails:

- Writes it made on the **default connection** are rolled back.
- Writes it made on **any other connection** are **not** rolled back by this mechanism, and must be
  verified manually before resuming. A seeder that writes to a non-default connection is
  responsible for wrapping those writes in its own transaction on that connection — the project's
  connection-affinity tests already forbid `DB::transaction(` inside a seeder file, so this is the
  existing convention, not a new obligation.

The failure message printed to the console and written to the log repeats this caveat verbatim, so
whoever is reading a broken release sees it without reading the source.

### Gotchas for seeder and test authors

- **`Setting::query()->forceCreate()` silently no-ops.** `Setting::requiresApprovalWhen()` shadows
  `HasApprovals`'s version and returns `true` for any fillable-field change, so the approval
  package's saving listener cancels every direct create/update on `Setting` — including
  `forceCreate()`. When a seeder test needs a real, already-persisted `Setting` row (for example to
  simulate one written before the reconciler existed), use
  `Setting::factory()->persistedWithoutApprovalCapture()->create([...])` instead.
- **`nwidart/laravel-modules` keys its module arrays by lowercase name; `core_settings.module`
  does not.** `Nwidart\Modules\FileRepository::scan()` keys `all()`/`allEnabled()` by
  `strtolower($name)`, but `core_settings.module` stores the module's declared case (`Core`, `MES`,
  ...). `array_key_exists('Core', Module::allEnabled())` is therefore always `false`, misclassifying
  every real module as absent. Resolve module state with `Module::find($name)?->isEnabled()`
  instead — `find()` lowercases internally. `ModuleStateResolver` already does this; reach for it
  rather than re-deriving module state from the array helpers.

## Features

### Requirements

-   PHP >= 8.5
-   Laravel 12.0+
-   **PHP Extensions:**

    -   `ext-curl`: Provides support for URL requests.
    -   `ext-json`: Enables JSON serialization and deserialization.
    -   `ext-redis`: Allows interaction with Redis databases.
    -   `ext-pcntl`: Provides process control functions.
    -   `ext-posix`: Offers access to POSIX functions.
    -   `ext-intl`: Provides internationalization services.
    -   `ext-sockets`: Provides low-level networking interface.

### Installed Packages

The Core Module utilizes several packages to enhance its functionality. Below is a list of the key packages included in the `composer.json` file:

-   **Database and ORM:**

    -   [doctrine/dbal](https://github.com/doctrine/dbal): Database abstraction layer.
    -   [laravel/fortify](https://github.com/laravel/fortify): Authentication scaffolding.
    -   [overtrue/laravel-versionable](https://github.com/overtrue/laravel-versionable): Model versioning.
    -   [spatie/laravel-permission](https://github.com/spatie/laravel-permission): Roles and permissions.
    -   [staudenmeir/laravel-adjacency-list](https://github.com/staudenmeir/laravel-adjacency-list): Hierarchical data handling.
    -   [spatie/eloquent-sortable](https://github.com/spatie/eloquent-sortable): Ordering helpers for Eloquent.

-   **UI / Admin:**

    -   [filament/filament](https://github.com/filamentphp/filament): Admin panel toolkit (v4).
    -   Built-in Filament environment indicator (Core plugin): env badge + module versions dropdown for super-admins.
    -   `filament:make-resources` — scaffold Filament resources for `App` or custom (non-owned) modules; injects `HasTable` / `HasForm` / `HasRecords` via rebound ClassGenerators. See `docs/rag/MODULE.md` (Filament scaffolding).

-   **Media:**

    -   [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary): Media handling for models.
    -   Core owns the media foundation: the shared `vend_media` table (guarded migration), the app-wide `media_model` (`Modules\Core\Models\Media`, soft-delete + version aware with a computed `expires_at`), the `file_namer` (`Modules\Core\Helpers\MediaFileNamer`), and the generic soft-delete-aware lifecycle trait `Modules\Core\Helpers\HasMedia`. Any module that needs attachments uses `HasMedia`; rows live in `vend_media`. The full `config/media-library.php` is Core-owned.
    -   Env: `MEDIA_DISK` (default `media-library`), `MAX_FILE_SIZE` (bytes, default 10MB), `MEDIA_QUEUE` (default `media-library`), `QUEUE_CONVERSIONS_BY_DEFAULT`, `QUEUE_CONVERSIONS_AFTER_DB_COMMIT`, `IMAGE_DRIVER` (default `imagick`), `FFMPEG_PATH`, `FFPROBE_PATH`, `MEDIA_DOWNLOADER_SSL`, `MEDIA_PREFIX`.

-   **Logging and Monitoring:**

    -   [hedii/laravel-gelf-logger](https://github.com/hedii/laravel-gelf-logger): Graylog via GELF.
    -   [laravel/horizon](https://github.com/laravel/horizon): Queue dashboard and management.
    -   **Error fingerprinting** (`app/Logging/Fingerprint`): a dependency-free, ordered normalization rule chain (`StripStackTraces`, `CollapseVolatilePayloads`, `CollapseSqlState`, `SubstituteUuidIpHex`, value-position-only `SubstituteNumbersInValuePosition`) plus a `Fingerprinter` that hashes `kind+module+class+file+function+normalized message` — the line is deliberately excluded so a refactor does not fork a group, and a 404 and a 500 stay distinct. `GelfErrorFingerprintResolver` (in-process) and SAO's payload path share this chain, so one error yields one key however it is captured.

-   **User Management:**

    -   [lab404/laravel-impersonate](https://github.com/404labfr/laravel-impersonate): User impersonation.
    -   [stephenlake/laravel-approval](https://github.com/stephenlake/laravel-approval): Approval workflow.
    -   [laravel/socialite](https://github.com/laravel/socialite): OAuth providers.

-   **API and Documentation:**

    -   [wotz/laravel-swagger-ui](https://github.com/wotzebra/laravel-swagger-ui): Swagger UI hosting.
    -   [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger): Swagger annotations.

-   **Search and Caching:**

    -   [elasticsearch/elasticsearch](https://github.com/elasticsearch/elasticsearch): Elasticsearch client.
    -   [theodo-group/llphant](https://github.com/theodo-group/llphant): Elasticsearch indexing helpers.
    -   [babenkoivan/elastic-scout-driver](https://github.com/babenkoivan/elastic-scout-driver): Scout driver for Elasticsearch.
    -   [babenkoivan/elastic-scout-driver-plus](https://github.com/babenkoivan/elastic-scout-driver-plus): Enhanced Elasticsearch features.
    -   [typesense/typesense-php](https://github.com/typesense/typesense-php): Typesense client.
    -   [laravel/scout](https://github.com/laravel/scout): Scout full-text abstraction.

-   **Spatial Data:**

    -   [matanyadaev/laravel-eloquent-spatial](https://github.com/matanyadaev/laravel-eloquent-spatial): Spatial types for Eloquent.

-   **Development and Testing:**

    -   [pestphp/pest](https://github.com/pestphp/pest) (+ stressless, type-coverage, laravel plugins).
    -   [laravel/pint](https://github.com/laravel/pint): Code style fixer.
    -   [nunomaduro/phpinsights](https://github.com/nunomaduro/phpinsights): Quality insights.
    -   [peckphp/peck](https://github.com/peckphp/peck): Typo checker.
    -   [rector/rector](https://github.com/rectorphp/rector): Automated refactoring.
    -   [driftingly/rector-laravel](https://github.com/driftingly/rector-laravel): Rector rules for Laravel.
    -   [larastan/larastan](https://github.com/nunomaduro/larastan): PHPStan for Laravel.
    -   [barryvdh/laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper): IDE helpers.
    -   [laravel/boost](https://github.com/laravel/boost): Local dev helper.
    -   [laravel/pail](https://github.com/laravel/pail): CLI log viewer.

### Environment (principali variabili)

-   Feature toggles: `ENABLE_USER_REGISTRATION`, `ENABLE_SOCIAL_LOGIN`, `ENABLE_USER_LICENSES`, `ENABLE_USER_2FA`, `VERIFY_NEW_USER`, `ENABLE_DYNAMIC_ENTITIES`, `ENABLE_DYNAMIC_GRIDUTILS`, `EXPOSE_CRUD_API`, `FORCE_HTTPS`.
-   Data retention: `SOFT_DELETES_EXPIRATION_DAYS`.
-   Search: `VECTOR_SEARCH_ENABLED`, `VECTOR_SEARCH_PROVIDER` (embeddings generation requires AI module).
-   Standard stack: `DB_*`, `REDIS_*`, `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION=failover`, `FILESYSTEM_DISK`, `LOG_*`.

### Additional Functionalities

The Core Module includes built-in features such as:

-   User management with multi-level roles and permissions.
-   Temporal user validity (`valid_from` / `valid_to` on `users` for temporary accounts that auto-expire).
-   Email verification for new users.
-   Command-line tools for user registration and model management.
-   Redis caching for improved performance.
-   Automatic indexing of entities with Elasticsearch (embeddings generation requires AI module).
-   **Event orchestration** for search indexing and modification moderation ([docs/EVENT_ORCHESTRATION.md](docs/EVENT_ORCHESTRATION.md)).
-   **Adaptive search matching** user/API behavior ([docs/rag/SEARCH_MATCHING_USER.md](docs/rag/SEARCH_MATCHING_USER.md)) and developer/operator integration ([docs/rag/SEARCH_MATCHING_DEVELOPER.md](docs/rag/SEARCH_MATCHING_DEVELOPER.md)).
-   Enhanced Swagger documentation generation.
-   Utilities for translations and model versioning.
-   Support for Laravel Octane and Horizon for improved performance and queue management.
-   Multi entities and connections crud endpoints with standardized requests parameters
-   Multi entities and connections exposed interactive grid endpoints (each grid config carries an `operations` array of the ActionEnum operations the current user may perform on that entity; an entity with no permitted operation is omitted, replacing the old operation-less gate that only passed for superadmins)
-   Strongly validated requests for Core routes
-   Default common Response Formatter
-   App settings and configurable Cron-Jobs on db tables
-   Automatic localization set on user request
-   Preview middleware for pending models approvals and multiuser approval system
-   Models versioning functionalities, with rollback functionalities
-   Dynamic entities for non mapped models
-   Dynamic gridutils for non mapped models
-   CRUD API for non mapped models
-   CRUD-aligned graph expand/search/stats endpoints for every CRUD-resolvable entity ([docs/GRAPH_SYSTEM.md](docs/GRAPH_SYSTEM.md)).
-   User licenses
-   Locking system for models
-   Graylog logging
-   Social login integration with multiple providers
-   Spatial data support for geographical applications
-   Composite primary keys support
-   Adjacency list pattern for hierarchical data structures
-   Vector search capabilities with AI-powered embeddings
-   Typesense search engine integration

## Scripts

The Core Module provides several useful scripts for development and maintenance:

### Code Quality and Testing

Run commands from the **Core module root** after `composer install`. Tests use `phpunit.xml` with `bootstrap="vendor/autoload.php"` (same idea as other Laraplate modules), so a local `vendor/` directory is required when working in the submodule.

```bash
# Run all tests and quality checks
composer test

# Run specific test suites
composer test:unit          # Run unit tests with coverage
composer test:type-coverage # Check type coverage (target: 100%)
composer test:typos         # Check for typos in code
composer test:lint          # Check code style
composer test:types         # Run PHPStan analysis
composer test:refactor      # Run Rector refactoring
```

**Test database:** By default tests use an in-memory SQLite database. The PHP extension `pdo_sqlite` is required (e.g. on Arch: `php-sqlite`). If it is not available, the suite falls back to MySQL: set `DB_HOST`, `DB_DATABASE` (e.g. `core_test`), `DB_USERNAME`, `DB_PASSWORD` and ensure the database exists.

### Code Quality Tools

```bash
# Code style and IDE helpers
composer lint               # Fix code style and generate IDE helpers

# Static analysis
composer check              # Run PHPStan analysis
composer fix                # Run PHPStan analysis with auto-fix
composer refactor           # Run Rector refactoring
```

### Version Management

```bash
# Version bumping
composer version:major      # Bump major version
composer version:minor      # Bump minor version
composer version:patch      # Bump patch version
```

### Development Setup

```bash
# Setup Git hooks
composer setup:hooks
```

### Other References

Core Module takes inspiration from, but does not directly require, libraries such as:

-   [sfolador/laravel-locked](https://github.com/sfolador/laravel-locked)
-   [reshadman/laravel-optimistic-locking](https://github.com/reshadman/laravel-optimistic-locking)
-   [vicgutt/laravel-inspect-db](https://github.com/VicGUTT/laravel-inspect-db)

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or correction.
3. Send a pull request.

## License

Core Module is open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).

## TODO and FIXME

This section tracks all pending tasks and issues that need to be addressed in the Core Module.

### High Priority

- [ ] **Database Compatibility Testing** - `Modules/Core/app/Models/DynamicEntity.php:104`
  - Test DynamicEntity with Oracle and SQL Server databases
  - Current implementation may not be fully compatible

- [ ] **Authentication Events Fix** - `Modules/Core/app/Models/Concerns/HasValidations.php:97`
  - FIXME: No events before retrieved, current implementation queries and then checks permissions
  - Need to implement proper event handling for user authorization

- [ ] **Development Middleware Cleanup** - `bootstrap/app.php:51`
  - TODO: Remove temporary middleware removals at end of development
  - Currently removes ValidateCsrfToken, EnsureEmailIsVerified, and AuthenticateSession

### Medium Priority

- [ ] **Database Index Optimization** - `Modules/Core/database/migrations/2019_05_31_042934_create_versions_table.php:26`
  - TODO: Consider adding index on versionable_type and versionable_id columns
  - Evaluate performance impact and implement if beneficial

- [ ] **Strict Mode Configuration** - `Modules/Core/app/Providers/CoreServiceProvider.php:298`
  - TODO: Strict mode prevents eager loading, application not yet ready
  - Need to review and implement proper eager loading strategies

- [ ] **CRUD Helper Relations** - `Modules/Core/app/Crud/CrudHelper.php:52`
  - TODO: Missing columns for relations when foreign key is on main table
  - Need to implement proper relation handling

- [ ] **Filter Grouping** - `Modules/Core/app/Crud/CrudHelper.php:99`
  - TODO: Need to implement filter disassembly and grouping for single relations
  - Current implementation may not handle complex filter scenarios properly

- [ ] **Sublevel Validation** - `Modules/Core/app/Crud/CrudHelper.php:255`
  - TODO: Current validation only works for first sublevel
  - Need to extend to support multiple sublevels

### Low Priority

- [ ] **Preview Record Management** - `Modules/Core/app/Http/Controllers/CrudController.php:304`
  - TODO: How to handle record preview? What to do with pending changes?
  - Need to implement proper preview functionality

- [ ] **Grid Request Data Completion** - `Modules/Core/app/Grids/Casts/GridRequestData.php:138`
  - TODO: Need to complete implementation
  - Current implementation is incomplete

- [ ] **Grid Request Entity Handling** - `Modules/Core/app/Grids/Requests/GridRequest.php:48`
  - TODO: Need entity or start from grid entity and check requested columns
  - Clarify entity handling strategy

- [ ] **Grid Components Review** - Multiple files
  - TODO: Review and improve Option component (`Modules/Core/app/Grids/Components/Option.php:37`)
  - TODO: Review and improve Funnel component (`Modules/Core/app/Grids/Components/Funnel.php:43`)
  - TODO: Test Grid component implementation (`Modules/Core/app/Grids/Components/Grid.php:134`)

- [ ] **Versioning Implementation** - `Modules/Core/app/Models/Concerns/HasVersions.php:161,166`
  - TODO: May need override for multiple primary keys
  - TODO: Complete implementation for versioning functionality

- [ ] **Entity Definition Testing** - `Modules/Core/app/Grids/Definitions/Entity.php:323,779`
  - TODO: May induce false paths if same sub-name exists in different sub-relations
  - TODO: Verify implementation, currently only sketched

### Notes

- Most TODO items are related to edge cases and advanced features
- Several items require testing with different database systems
- Some components need completion of implementation details
- Priority should be given to high-priority items that affect core functionality
