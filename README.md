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
ELASTICSEARCH_INDEX_PREFIX=						#elasticsearch index prefix
ELASTICSEARCH_QUEUE_CONNECTION=sync				#elasticsearch queue connection
ELASTICSEARCH_QUEUE=indexing					#elasticsearch queue name
ELASTICSEARCH_QUEUE_TIMEOUT=300					#elasticsearch queue timeout
ELASTICSEARCH_QUEUE_TRIES=3						#elasticsearch queue retry attempts
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

## Features

### Requirements

-   PHP >= 8.4
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

    -   [doctrine/dbal](https://github.com/doctrine/dbal): A database abstraction layer for PHP.
    -   [laravel/fortify](https://github.com/laravel/fortify): Provides authentication features for Laravel applications.
    -   [overtrue/laravel-versionable](https://github.com/overtrue/laravel-versionable): Adds versioning capabilities to Eloquent models.
    -   [spatie/laravel-permission](https://github.com/spatie/spatie-laravel-permission): Manages user roles and permissions.
    -   [staudenmeir/laravel-adjacency-list](https://github.com/staudenmeir/laravel-adjacency-list): Provides adjacency list pattern for hierarchical data.
    -   [awobaz/compoships](https://github.com/topclaudy/compoships): Enables composite primary keys in Laravel Eloquent.

-   **Logging and Monitoring:**

    -   [hedii/laravel-gelf-logger](https://github.com/hedii/laravel-gelf-logger): Sends logs to Graylog using the GELF format.
    -   [laravel/horizon](https://github.com/laravel/horizon): Provides a dashboard and code-driven configuration for Laravel queues.

-   **User Management:**

    -   [lab404/laravel-impersonate](https://github.com/404labfr/laravel-impersonate): Allows user impersonation for testing and support.
    -   [stephenlake/laravel-approval](https://github.com/stephenlake/laravel-approval): Implements an approval workflow for models.
    -   [laravel/socialite](https://github.com/laravel/socialite): Provides OAuth authentication for Laravel applications.

-   **API and Documentation:**

    -   [wotz/laravel-swagger-ui](https://github.com/wotzebra/laravel-swagger-ui): Generates Swagger UI documentation for your API.
    -   [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger): Provides Swagger annotations for Laravel.

-   **Search and Caching:**

    -   [elasticsearch/elasticsearch](https://github.com/elasticsearch/elasticsearch): Integrates Elasticsearch for advanced search capabilities.
    -   [theodo-group/llphant](https://github.com/theodo-group/llphant): Provides a library for handling Elasticsearch indexing.
    -   [babenkoivan/elastic-scout-driver](https://github.com/babenkoivan/elastic-scout-driver): Laravel Scout driver for Elasticsearch.
    -   [babenkoivan/elastic-scout-driver-plus](https://github.com/babenkoivan/elastic-scout-driver-plus): Enhanced Laravel Scout driver for Elasticsearch.
    -   [typesense/typesense-php](https://github.com/typesense/typesense-php): PHP client for Typesense search engine.
    -   [laravel/scout](https://github.com/laravel/scout): Laravel Scout provides a simple, driver-based solution for adding full-text search to your Eloquent models.

-   **Spatial Data:**

    -   [matanyadaev/laravel-eloquent-spatial](https://github.com/matanyadaev/laravel-eloquent-spatial): Provides spatial data support for Laravel Eloquent models.

-   **Development and Testing:**

    -   [pestphp/pest](https://github.com/pestphp/pest): A testing framework for PHP.
    -   [pestphp/pest-plugin-laravel](https://github.com/pestphp/pest-plugin-laravel): Adds Laravel-specific testing features to Pest.
    -   [pestphp/pest-plugin-stressless](https://github.com/pestphp/pest-plugin-stressless): Stress testing plugin for Pest.
    -   [pestphp/pest-plugin-type-coverage](https://github.com/pestphp/pest-plugin-type-coverage): Type coverage plugin for Pest.
    -   [laravel/pint](https://github.com/laravel/pint): Laravel's code style fixer.
    -   [nunomaduro/phpinsights](https://github.com/nunomaduro/phpinsights): PHP quality checker.
    -   [peckphp/peck](https://github.com/peckphp/peck): PHP typo checker.
    -   [rector/rector](https://github.com/rectorphp/rector): Automated PHP code refactoring tool.

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
