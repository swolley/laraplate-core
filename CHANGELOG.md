# Changelog

All notable changes to this project will be documented in this file.

## [unreleased]

### 🐛 Bug Fixes

- Standardize naming conventions for settings and improve table configurations

### ⚙️ Miscellaneous Tasks

- Update dependencies in composer.json

## [1.36.0] - 2026-01-30

### 🚀 Features

- Implement pending approval notifications and command

### 🚜 Refactor

- Update authentication method and clean up configuration files
- Simplify DocsController and introduce RouteServiceProvider override
- Replace PermissionChecker with AuthorizationService for permission handling
- Enhance ModificationsTable configuration and clean up HasTable trait

## [1.35.0] - 2026-01-22

### 🚜 Refactor

- Restructure Core module components and enhance documentation

## [1.34.0] - 2026-01-21

### 🚀 Features

- Introduce MoveEmbeddingTable command and ModelEmbedding model

## [1.33.2] - 2026-01-21

### 🚜 Refactor

- Deprecate AI configuration in README and update documentation
- Enhance CRUD helper and service for computed methods and relations

## [1.33.1] - 2026-01-15

### 🚜 Refactor

- Enhance ModuleDocGenerator to support abstract FormRequest classes

## [1.33.0] - 2026-01-15

### 🚜 Refactor

- Update command descriptions and remove deprecated files

## [1.32.1] - 2026-01-14

### 🚜 Refactor

- Enhance CRUD operations and introduce DTOs for improved data handling

## [1.32.0] - 2026-01-12

### 🚜 Refactor

- Update RecentActivityWidget and HasTable trait for improved functionality

## [1.31.0] - 2026-01-09

### 🚀 Features

- Add Swagger and Welcome pages for API documentation

### 🚜 Refactor

- Update permission handling and error management in search index functions
- Update user model references and enhance documentation structure

## [1.30.8] - 2025-12-22

### 🚜 Refactor

- Enhance BatchSeeder and Searchable trait functionality

## [1.30.7] - 2025-12-22

### ⚙️ Miscellaneous Tasks

- Update dependencies and version constraints in composer files

## [1.30.6] - 2025-12-22

### 🚜 Refactor

- Update Elasticsearch client configuration for improved clarity

## [1.30.4] - 2025-12-22

### 🚜 Refactor

- Update closures to static functions for improved performance and consistency
- Remove #[Override] attributes from getAttribute and setAttribute methods in HasTranslations trait
- Update Elasticsearch configuration and improve BatchSeeder logic

### 📚 Documentation

- Update README and implement PHP attributes for type safety

## [1.30.3] - 2025-12-19

### 🚜 Refactor

- Enhance error handling in BatchSeeder and update UserFactory for unique username generation
- Enhance translatable field handling in HasTranslations trait

### ⚙️ Miscellaneous Tasks

- Update cliff.toml to modify commit filtering settings

## [1.30.2] - 2025-12-19

### ⚙️ Miscellaneous Tasks

- Update cliff.toml and version.sh for improved commit parsing and version update handling

## [1.30.1] - 2025-12-19

### 🚜 Refactor

- Improve index creation logic and enhance Elasticsearch service tests

## [1.30.0] - 2025-12-17

### 🚜 Refactor

- Update CronJob and Permission factories, migrations, and tests for improved functionality and consistency
- Update Filament resource tests for improved structure and consistency
- Enhance BatchSeeder and translation traits for improved performance and clarity

### ⚙️ Miscellaneous Tasks

- Update Pest dependencies and improve README documentation
- Update PHP version requirement and refactor action classes for improved type safety

## [1.29.0] - 2025-12-13

### 🚀 Features

- Introduce custom CacheManager and enhance error handling in various components
- Add new actions for managing Swagger documentation, grid configurations, site settings, translations, and user information

### 🚜 Refactor

- Update user creation and query handling for improved consistency
- Clean up code structure and enhance readability across multiple files
- Update PHPStan configuration and enhance model traits with type annotations

## [1.28.1] - 2025-12-05

### 🚜 Refactor

- Enhance vector search capabilities in Elasticsearch and Typesense engines
- Improve batch seeding process with enhanced progress reporting and error handling

## [1.28.0] - 2025-12-03

### 🚜 Refactor

- Enhance batch seeding process with parallel execution and improved error handling

## [1.27.0] - 2025-12-02

### 🚜 Refactor

- Enhance translation services and clean up dependencies

## [1.26.0] - 2025-11-17

### 🚜 Refactor

- Streamline code structure and enhance memory management across various components
- Remove Compoships overrides and clean up composer dependencies
- Update database transaction handling and enhance batch processing
- Remove password hashing from user creation and updates

## [1.25.0] - 2025-10-21

### 🚜 Refactor

- Improve type hinting and exception handling in grid utilities and compoships
- Optimize memory management and code structure in console commands
- Optimize memory management and code structure across various components
- Enhance form and table utilities for improved functionality

## [1.24.0] - 2025-10-14

### 🚀 Features

- Update composer dependencies and enhance project configuration

## [1.23.0] - 2025-10-13

### 🚀 Features

- Introduce SearchEngineHealthTableWidget and enhance CacheHealth page

## [1.22.0] - 2025-10-01

### 🚀 Features

- Add HorizonHealth page and corresponding view for Laravel Horizon integration

## [1.21.0] - 2025-09-30

### 🚀 Features

- Enhance search functionality and update dependencies

### 🐛 Bug Fixes

- Add check for up-to-date version in version.sh script
- Improve return handling in is_already_tagged function in version.sh
- Refactor return statements in determine_release_type function in version.sh
- Refactor determine_release_type function to use echo for version type determination
- Enhance version.sh script to determine commit importance and release type
- Clean up debug output and streamline commit analysis in version.sh

### ⚙️ Miscellaneous Tasks

- Update versioning mechanism in composer.json and enhance version.sh script

## [1.20.0] - 2025-09-27

### 🚀 Features

- Implement embedding table migration command and enhance search engine functionality

### 🚜 Refactor

- Enhance code structure and improve type declarations

## [1.19.2] - 2025-09-25

### 🐛 Bug Fixes

- Ensure URL protocol and handle embeddings count mismatch

## [1.19.1] - 2025-09-20

### 🐛 Bug Fixes

- Correct function name and add uncommitted changes check in version script

### 🚜 Refactor

- Introduce HasRecords and HasTable traits to streamline resource management

## [1.19.0] - 2025-09-19

### 🚜 Refactor

- Improve code consistency and enhance documentation
- Enhance Filament resource configurations and improve code structure
- Update BaseTable to enhance functionality and improve query handling

## [1.17.0] - 2025-08-19

### 🚜 Refactor

- Enhance resource configurations and improve column handling

## [1.16.1] - 2025-08-18

### 🚜 Refactor

- Improve code readability and structure across multiple files
- Enhance method validation and error handling in CrudHelper

## [1.16.0] - 2025-08-07

### 🚀 Features

- Enhance Swagger generation and CRUD API middleware
- Introduce Sentence Transformers integration for embedding generation

### ⚙️ Miscellaneous Tasks

- Update composer.json and enhance BatchSeeder functionality

## [1.15.1] - 2025-07-24

### 🐛 Bug Fixes

- Update git cliff command in version.sh for changelog generation

## [1.15.0] - 2025-07-24

### 🚀 Features

- Add new Filament resources for ACL, CronJob, License, Permission, Role, Setting, and User management

### 🐛 Bug Fixes

- Update post-commit hook script path for correct execution

### 🚜 Refactor

- Improve type safety and code clarity in ElasticsearchService
- Remove post-commit hook and enhance setup script
- Enhance code structure and type safety across multiple files
- Improve code clarity and type safety in multiple files
- Enhance type safety and clarity in PHPDoc comments across multiple files
- Update code formatting and improve type safety in multiple files
- Enhance type safety and code clarity in multiple files
- Enhance Elasticsearch and Typesense engine implementations for improved modularity and type safety
- Clean up PHPDoc comments in User model for improved clarity
- Update BatchSeeder and HasValidations for improved functionality and clarity
- Update module.json and improve code formatting across multiple files

### 📚 Documentation

- Update README.md with caution note and clarify project status

### ⚙️ Miscellaneous Tasks

- Add changelog and configuration for git-cliff
- Add configuration files for static analysis and code formatting
- Add versioning scripts to composer.json for improved version management

## [1.14.2] - 2025-05-05

### 🚜 Refactor

- Remove unnecessary comment in User model for improved code clarity
- Enhance type safety and code clarity across multiple files
- Fix method signature for improved type safety in ResponseBuilder
- Enhance type safety and code clarity in multiple classes

## [1.14.1] - 2025-05-05

### 🚀 Features

- Integrate Elasticsearch and Typesense for enhanced search capabilities

### 🚜 Refactor

- Improve null handling and code clarity across multiple files
- Enhance command structure and configuration management
- Update configuration management and clean up code
- Streamline code structure and enhance type safety
- Improve code clarity and consistency in various classes

## [1.14.0] - 2025-04-10

### 🚀 Features

- Enable model embedding generation for AI integration

## [1.13.1] - 2025-04-07

### 🚜 Refactor

- Enhance CommonMigrationFunctions and introduce CustomSoftDeletingScope

## [1.13.0] - 2025-04-06

### 🚀 Features

- Introduce BatchSeeder and DevCoreDatabaseSeeder for efficient data seeding

## [1.12.3] - 2025-04-06

### 🚜 Refactor

- Update PermissionsRefreshCommand and HasSeedersUtils for improved clarity and functionality

## [1.12.2] - 2025-04-05

### ⚙️ Miscellaneous Tasks

- Update PHP version requirement and refactor code for consistency

## [1.12.1] - 2025-03-31

### 🚀 Features

- Add AddRouteCommentsCommand for automatic route comment generation

## [1.12.0] - 2025-03-31

### 🚀 Features

- Enhance caching functionality and add route comments command

## [1.11.0] - 2025-03-28

### 🚜 Refactor

- Update command imports and enhance model creation logic

## [1.10.0] - 2025-03-20

### 🚀 Features

- Implement Elasticsearch job management

## [1.9.0] - 2025-03-11

### 🚀 Features

- Add command to clear expired soft-deleted models

## [1.8.1] - 2025-03-07

### 🚀 Features

- Enhance authentication configuration and CrudController

## [1.8.0] - 2025-03-07

### ⚙️ Miscellaneous Tasks

- Update project dependencies and configuration files

## [1.7.3] - 2025-03-03

### 🚜 Refactor

- Add type hints to User model relationships

## [1.7.2] - 2025-02-28

### 🚜 Refactor

- Modernize code with PHP 8.x comparison and method improvements

## [1.7.1] - 2025-02-28

### 🚜 Refactor

- Modernize PHP codebase with PHP 8.x features and code quality improvements

## [1.7.0] - 2025-02-28

### 🚀 Features

- Add LDAP support and enhance console commands

## [1.6.0] - 2025-02-26

### 🚀 Features

- Implement flexible authentication providers with LDAP and Socialite support

## [1.5.0] - 2025-02-21

### 🚜 Refactor

- Extract CRUD operations to a reusable trait and optimize Elasticsearch query generation

## [1.4.0] - 2025-02-19

### 🚜 Refactor

- Improve dependency injection and benchmarking across core components

## [1.3.0] - 2025-02-18

### 🚀 Features

- Enhance cache management and dependency injection

## [1.2.0] - 2025-02-16

### 🚀 Features

- Improve Grid processing and add benchmarking utility

## [1.1.1] - 2025-02-04

### 🚀 Features

- Improve user creation and error handling

## [1.1.0] - 2025-01-29

### 🚀 Features

- Improve Swagger documentation generation and configuration

## [1.0.0] - 2025-01-25

<!-- generated by git-cliff -->
