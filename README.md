<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png" width="400" alt="Laravel Logo" />
    </a>
</p>
<p>&nbsp;</p>

> **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided... or better yet, collaborate with me to create the definitive Laravel boilerplate; that's the right place to instroduce your ideas. Let me know your ideas...

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
AUTH_MODEL=Modules\Core\Models\User				#authentication model

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

#ai
OPENAI_API_KEY=									#openai api key
OPENAI_API_URL=                                 #openai compatible api url
OPENAI_MODEL=                                   #openai model
OLLAMA_API_URL=                                 #ollama compatible api url
OLLAMA_MODEL="llama3.2:3b"						#ollama model

#search
SCOUT_DRIVER=typesense                          #actually supperted drivers with full functionalities (typesense, elasticsearch)
VECTOR_SEARCH_ENABLED=true                      #create embeddings with ai functionalities before indexing in search engine
EMBEDDING_PROVIDER=openai                       #actually supported embedding generator provider (openai, ollama)
SEARCH_ENGINE=elasticsearch						#default search engine
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

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasVersions;

class ... extends Model
{
    use HasVersions;

	protected $versionable = [ /** versionable fields */ ];
	protected $dontVersionable = [ /** fields not versionable */ ];
}
```

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
use Modules\Core\Helpers\HasValidations;

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

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidity;

class ... extends Model
{
    use HasValidity;

	protected static $valid_from_column = 'valid_from';
	protected static $valid_to_column = 'valid_to';
}
```

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
    -   [pxlrbt/filament-environment-indicator](https://github.com/pxlrbt/filament-environment-indicator): Environment badge in Filament.

-   **Media:**

    -   [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary): Media handling for models.

-   **Logging and Monitoring:**

    -   [hedii/laravel-gelf-logger](https://github.com/hedii/laravel-gelf-logger): Graylog via GELF.
    -   [laravel/horizon](https://github.com/laravel/horizon): Queue dashboard and management.

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
-   Search/AI: `VECTOR_SEARCH_ENABLED`, `VECTOR_SEARCH_PROVIDER` (quando abilitato).
-   Standard stack: `DB_*`, `REDIS_*`, `SESSION_*`, `CACHE_STORE`, `QUEUE_CONNECTION=failover`, `FILESYSTEM_DISK`, `LOG_*`.

### Additional Functionalities

The Core Module includes built-in features such as:

-   User management with multi-level roles and permissions.
-   Email verification for new users.
-   Command-line tools for user registration and model management.
-   Redis caching for improved performance.
-   Automatic indexing of entities with Elasticsearch and OpenAI support for tokenization.
-   Enhanced Swagger documentation generation.
-   Utilities for translations and model versioning.
-   Support for Laravel Octane and Horizon for improved performance and queue management.
-   Multi entities and connections crud endpoints with standardized requests parameters
-   Multi entities and connections exposed interactive grid endpoints
-   Strongly validated requests for Core routes
-   Default common Response Formatter
-   App settings and configurable Cron-Jobs on db tables
-   Automatic localization set on user request
-   Preview middleware for pending models approvals and multiuser approval system
-   Models versioning functionalities, with rollback functionalities
-   Dynamic entities for non mapped models
-   Dynamic gridutils for non mapped models
-   CRUD API for non mapped models
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

Core Module is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## TODO and FIXME

This section tracks all pending tasks and issues that need to be addressed in the Core Module.

### High Priority

- [ ] **Database Compatibility Testing** - `Modules/Core/app/Models/DynamicEntity.php:104`
  - Test DynamicEntity with Oracle and SQL Server databases
  - Current implementation may not be fully compatible

- [ ] **Authentication Events Fix** - `Modules/Core/app/Helpers/HasValidations.php:97`
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

- [ ] **Versioning Implementation** - `Modules/Core/app/Helpers/HasVersions.php:161,166`
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


