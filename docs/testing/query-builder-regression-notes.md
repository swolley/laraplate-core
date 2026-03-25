# QueryBuilder Regression Notes

## Why this file exists

This note captures test patterns that previously caused regressions in CRUD request-to-query behavior.

## Important edge cases

- Relation filters must use `where` inside `whereHas` / `orWhereHas` callbacks.
  - Propagating `orWhere` into relation subqueries can bypass relation constraints.
- SQL `LIKE` patterns should avoid `_` in token-style fixtures unless wildcard behavior is intended.
  - `_` matches a single character in SQL and can create false positives.
- Tests using `GREAT_EQUALS` on IDs should create the threshold fixture last or constrain candidate IDs.
  - Otherwise unrelated fixtures with higher IDs can be included.
- Nested relation eager-load constraints should be asserted on both parent and child relations.
  - Example: filtering on `roles.permissions.name` should also constrain loaded `roles`.

## Aggregate method mapping

- `ColumnType::AVG` must map to Eloquent `withAvg` (not `withAverage`).
- Nested aggregates (`roles.permissions.id` with `MIN`/`MAX`) are applied on relation callbacks, not on the root query SQL.
