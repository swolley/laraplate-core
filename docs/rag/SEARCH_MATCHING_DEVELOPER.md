# Adaptive search matching — developer and operator guide

## Architecture

Adaptive matching is resolved once before retrieval:

```text
SearchRequest
  -> SearchRequestData
  -> AdvancedSearchService
  -> SearchQueryAnalyzer
  -> TextMatchOptionsResolver
  -> ResolvedTextMatch
  -> EnsembleSearchService
  -> Scout builder text_match option
  -> engine adapter
```

HTTP parameters never reach an engine directly. Engines consume only validated `TextMatchOptions`.

## Domain objects

- `TextMatchPreference`: `auto`, `strict`, `balanced`, `tolerant`.
- `SearchTokenKind`: numeric, UUID, email, structured identifier, acronym, short token, or word.
- `SearchQueryAnalyzer`: preserves original case/punctuation while producing normalized classifications.
- `SearchQueryAnalysis`: counts meaningful, protected, and typo-eligible tokens.
- `TextMatchOptionsResolver`: applies defaults, preference presets, adaptive rules, granular overrides, and protected-token invariants.
- `ResolvedTextMatch`: carries analysis, requested/effective preference, effective options, and response metadata.

## Resolution precedence

1. Analyze the original query.
2. Select the automatic behavior.
3. Apply an explicit preference.
4. Apply granular overrides.
5. Enforce identifier protection.
6. Clamp values in `TextMatchOptions`.
7. Translate through the active engine and report degradation.

The current cross-engine safety rule disables typo expansion for a complete mixed keyword query when any protected token is present. Set `identifier_typos=true` only when the caller explicitly accepts fuzzy identifiers.

## Configuration

Defaults and replaceable presets live in `Modules/Core/config/search.php`:

```php
'text_matching' => [
    'defaults' => [
        'typo_tolerance' => true,
        'max_edits' => 1,
        'prefix' => true,
        'minimum_term_length' => 4,
        'two_edit_minimum_term_length' => 8,
        'exact_match_boost' => 2.0,
        'operator' => 'and',
        'minimum_should_match' => 100,
        'fuzzy_token_limit' => 1,
        'identifier_typos' => false,
    ],
    'preferences' => [
        'strict' => [],
        'balanced' => [],
        'tolerant' => [],
    ],
],
```

Preferences must remain configuration presets. Engine code must not branch on preference names.

Direct Scout callers can pass effective or override options through:

```php
$builder->options[TextMatchOptionsResolver::BUILDER_OPTION] = [
    'preference' => 'balanced',
    'max_edits' => 1,
];
```

The orchestrated pipeline passes already resolved granular options to keyword and hybrid strategies. Pure vector retrieval receives no text-match options.

## Engine adapters

### Elasticsearch

`ElasticsearchEngine::buildTextMatchQuery()` builds a fuzzy/prefix `multi_match` plus an exact phrase boost. `minimum_should_match` is emitted for relaxed `OR` queries. Fuzziness is disabled for protected-only queries unless identifier typo permission is effective.

### Typesense

`TypesenseEngine::buildTextMatchParameters()` emits `num_typos`, `prefix`, `min_len_1typo`, `min_len_2typo`, and `prioritize_exact_match`. Capabilities declare semantics that are only approximate.

### Database

`DatabaseTextMatchCompiler` always produces bound SQL values. Field identifiers originate from model/search schema metadata and are wrapped by the connection grammar.

Portable drivers use case-insensitive `LIKE`. PostgreSQL optionally uses `strict_word_similarity()`:

```env
SEARCH_DATABASE_PG_TRGM_ENABLED=true
```

The database must also have:

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
```

For production performance, schema fields now declare their physical intent explicitly with `IndexType::Fuzzy`, `IndexType::FullText`, or `IndexType::Prefix`. Plain searchable text no longer implies a full-text database index.

Migration DDL is centralized in the existing `MigrateUtils`:

```php
MigrateUtils::prefixIndex($table, 'slug');
MigrateUtils::fuzzyIndex($tableName, 'name');
MigrateUtils::fullTextIndex($tableName, 'search_text', language: 'italian');
```

Call `prefixIndex()` inside `Schema::create()` and specialized methods after the table exists. PostgreSQL fuzzy indexes install `pg_trgm` and use GIN `gin_trgm_ops`; PostgreSQL full-text indexes use GIN `to_tsvector`. MySQL/MariaDB supports declared full-text indexes but intentionally degrades fuzzy DDL. SQLite keeps prefix B-trees and safely skips specialized DDL. Do not apply fuzzy/full-text indexes indiscriminately to codes, UUIDs, emails, enums, or short machine identifiers.

Oracle DDL maps a single declared fuzzy or full-text column to `CTXSYS.CONTEXT`. Short mutable labels default to `SYNC (ON COMMIT)`; prose defaults to manual synchronization. Query execution still remains on the portable fallback until the schema-aware `CONTAINS`/`SCORE` adapter, lexer/stoplist preferences, privilege checks, synchronization jobs, and rebuild handling are implemented. Never apply `UTL_MATCH` to arbitrary long columns.

Initial migration policy is deliberately conservative: names and short human titles use fuzzy indexes; slugs retain their existing B-tree indexes; prose requires a normalized physical `search_text` column before receiving full-text DDL. JSON component payloads are not indexed as database full text directly.

## Capabilities and degradation

Every `ISearchEngine` implements `textMatchCapabilities()`. Add new semantic controls to the portable contract only when each adapter can either translate or explicitly report them as degraded.

Response metadata is assembled from `ResolvedTextMatch::toMeta()` and engine capabilities. Never include additional raw protected token values in logs or telemetry; counts and kinds are sufficient.

## Testing requirements

Contract tests must cover:

- Unicode proper names and common misspellings;
- uppercase acronyms;
- structured codes, numbers, UUIDs, and emails;
- two-token `AND` behavior;
- medium and long minimum-match thresholds;
- strict and tolerant preference behavior;
- granular override precedence;
- explicit identifier typo opt-in;
- engine parameter translation;
- builder propagation and response metadata;
- portable database SQL and PostgreSQL trigram SQL.

Run:

```bash
php artisan test --compact Modules/Core/tests/Integration/Search
php artisan test --compact Modules/Core/tests/Integration/Http/Requests/AuthAndSearchRequestsTest.php
vendor/bin/pint --dirty
```
