# Adaptive search matching — user and API guide

## What adaptive matching does

Laraplate adjusts text matching to the shape of the query while preserving exact matches and identifiers. Short searches are conservative because they commonly represent names, acronyms, invoice numbers, SKUs, emails, UUIDs, or numeric identifiers. Longer natural-language searches may match a lower percentage of meaningful words to improve recall.

This behavior applies to orchestrated CRUD search on both the authenticated `/app` surface and the optional `/api/v1` surface. Existing requests remain valid.

## Default behavior

When `matching` is omitted or set to `auto`:

| Query shape | Behavior |
|-------------|----------|
| One acronym/code/number/email/UUID | Strict; no typo expansion |
| One ordinary word | Exact results first; prefix matching; at most one typo when long enough |
| Two meaningful words | Both words required; exact phrase preferred; limited typo expansion |
| Three to five meaningful words | Approximately 75% of meaningful words required |
| Six or more meaningful words | Approximately 65% of meaningful words required |

Italian and English stopwords are ignored only when deciding how many meaningful words the query contains. They remain part of the original engine query where appropriate.

Examples:

- `ACME`, `CRM`, `2026`, `INV-1042`, and `mario@example.it` remain strict.
- `Giusppe` may match `Giuseppe`, while exact `Giuseppe` ranks first.
- `Mario Rossi` requires both meaningful words.
- `fatture fornitori italiane scadute giugno` can match documents containing most, rather than necessarily all, meaningful words.

A mixed query containing a protected identifier, such as `ACME fatture`, is conservative and disables typo expansion for the complete keyword query by default. This guarantees comparable behavior across engines.

## Matching preferences

Use the optional `matching` query parameter:

```text
matching=auto
matching=strict
matching=balanced
matching=tolerant
```

- `auto` selects behavior from the query.
- `strict` disables typo tolerance and requires strict token matching.
- `balanced` provides moderate typo tolerance for eligible words.
- `tolerant` permits greater recall for long eligible words and longer queries, but does not weaken identifiers automatically.

Preferences are adaptive hints. They do not bypass identifier protection.

## Granular controls

Advanced callers may provide:

```text
matching_options[max_edits]=0|1|2
matching_options[prefix]=true|false
matching_options[operator]=and|or
matching_options[minimum_should_match]=1..100
matching_options[identifier_typos]=true|false
```

Example:

```http
GET /app/core/search/customers?qs=Mario%20Rossi&mode=orchestrated&matching=balanced&matching_options[max_edits]=1
```

To explicitly permit typo expansion for identifiers:

```http
GET /app/core/search/invoices?qs=INV-1042&mode=orchestrated&matching=tolerant&matching_options[identifier_typos]=true
```

Use identifier typo expansion sparingly because it can return incorrect codes or records.

## Response metadata

Orchestrated search responses expose the effective decision under `meta.search.matching` or the enclosing advanced-search metadata, depending on the CRUD response wrapper:

```json
{
  "matching": {
    "requested_preference": "auto",
    "effective_preference": "balanced",
    "significant_token_count": 2,
    "token_kinds": ["word", "word"],
    "protected_token_count": 0,
    "eligible_token_count": 2,
    "fuzzy_token_limit": 1,
    "options": {
      "max_edits": 1,
      "operator": "and",
      "minimum_should_match": 100
    },
    "degraded": []
  }
}
```

`degraded` identifies requested semantics that the active search engine cannot represent exactly. Result scores are not numerically comparable across different engines even when the matching semantics are the same.

## Engine expectations

- Elasticsearch offers native typo, prefix, boolean, phrase-boost, and token-threshold controls.
- Typesense offers native typo and prefix controls plus exact-match priority; some numeric boost and boolean details may be degraded.
- PostgreSQL can provide typo similarity when `pg_trgm` is installed and enabled.
- MySQL, MariaDB, SQLite, and the current Oracle database fallback use case-insensitive prefix/substring matching and report unsupported typo semantics as degraded.
- Oracle Text `CONTEXT` integration is a separate schema-aware feature and is not enabled by the portable database fallback.

