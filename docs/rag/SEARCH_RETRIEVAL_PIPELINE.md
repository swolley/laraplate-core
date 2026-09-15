# Search retrieval pipeline — developer and operator guide

How an orchestrated search request becomes a ranked list. Adaptive text matching (fuzziness,
required terms, engine translation) is a separate concern documented in
[SEARCH_MATCHING_DEVELOPER.md](./SEARCH_MATCHING_DEVELOPER.md) and
[SEARCH_MATCHING_USER.md](./SEARCH_MATCHING_USER.md).

## Entry point and mode

`CrudService::search()` picks the path from `SearchRequestData->mode` (`mode` query parameter,
default `auto`):

| `mode` | Path |
|--------|------|
| `orchestrated` | always `AdvancedSearchService` |
| `auto` (default) | `AdvancedSearchService` when `available()` (engine implements `ISearchEngine` **and** `supportsOrchestratedSearch()`), otherwise plain Scout |
| `basic` | always plain Scout, no orchestration |

An engine that does not support orchestration returns `AdvancedSearchResult::empty()` with
`meta['unsupported_driver'] = true` when `orchestrated` was forced.

## The five steps

```text
AdvancedSearchService::search()
  1. IQueryIntentParser::parse()        -> optional query expansion
  2. ISearchPlanner::safePlan()         -> the plan (which strategies, weights, rerank)
     applyEngineCapabilities()          -> plan downgraded to keyword if engine lacks kNN
  3. resolveVector()                    -> query embedding, or null
     TextMatchOptionsResolver::resolve() -> ResolvedTextMatch (lexical only)
  4. EnsembleSearchService::search()
       executeScoutSearch() x1 or x3    -> keyword | vector | hybrid, SEQUENTIAL
       fuseStrategies()                 -> weighted normalized score + RRF + agreement
  5. rerankTopK()                       -> IReranker over the fused top-K
```

### Step 2 — the plan decides how many strategies run

`FallbackSearchPlanner::fallbackPlan()` (Core, no LLM) applies two rules:

```php
$is_short   = mb_strlen($query) < 20;
$has_numbers = (bool) preg_match('/\d/', $query);
$use_vector  = config('search.vector_search.enabled', false) && ! $has_numbers;
```

| Condition | Strategies executed |
|-----------|---------------------|
| `VECTOR_SEARCH_ENABLED=false` | `keyword` only |
| query contains any digit | `keyword` only |
| engine without `supportsOrchestratedVectorSearch()` | `keyword` only (plan downgraded by `applyEngineCapabilities()`) |
| `ITextEmbedder` not bound (AI module absent or search orchestration disabled) | `keyword` only (`$vector === null`) |
| otherwise | `keyword` + `vector` + `hybrid` |

**One or three, never two**: `hybrid` runs only when both `use_fulltext` and `use_vector` are true.
The three Scout calls are **sequential**, not parallel. When a strategy does not run, its weight is
removed and the remaining weights are renormalized to sum to 1.0
(`renormalizeWeightsForExecutedStrategies()`), so a keyword-only run gives keyword weight 1.0 and
fusion becomes a pass-through.

`SearchOrchestratorAgent` (AI module) can replace the planner with an LLM-produced plan; it is
clamped and validated before use, and falls back to the same rule-based plan on any failure.

### Step 3 — the query vector is an AI-module capability

```php
if (($retrieval['use_vector'] ?? false) !== true || ! $this->app->bound(ITextEmbedder::class)) {
    return null;
}
```

`ITextEmbedder` is bound **only** by `AIServiceProvider`, and only when
`ai.features.search_orchestration.enabled` is true. Without the AI module the interface is unbound,
the query vector is `null`, and the pipeline stays keyword-only. This is independent of
`VECTOR_SEARCH_ENABLED`: both must hold.

What is lost without the AI module, per contract:

| Contract | Core fallback | Effect when AI is absent |
|----------|---------------|--------------------------|
| `ITextEmbedder` | none | vector and hybrid retrieval unavailable |
| `IReranker` | `HeuristicReranker` (pure PHP lexical scoring) | reranking still runs, coarser |
| `ISearchPlanner` | `FallbackSearchPlanner` (rules above) | plans by rules instead of LLM |
| `IQueryIntentParser` | `SimpleQueryIntentParser` | basic intent, no LLM expansion |

Only the embedder has no Core fallback. Lexical tokenization is not affected: it belongs to the
engine analyzers and to `TextMatchOptionsResolver`, both in Core.

### Step 4 — fusion is weighted score **plus** RRF, not RRF alone

Per strategy, hit scores are min-max normalized to `[0,1]` (`minMaxNormalizeScores()`), because BM25
and cosine similarity are not comparable. Then, for every id seen by at least one strategy:

```text
score = Σ_s (normalized_score_s × weight_s)          // weighted component
      + (Σ_s 1 / (rrf_k + rank_s)) × rrf_weight      // rank-based component
      + agreement_boost × (appearances / strategies) // only when appearances > 1
```

Defaults from the plan: `keyword_weight` 0.35, `vector_weight` 0.35, `hybrid_weight` 0.30,
`rrf_k` 60, `rrf_weight` 0.25, `agreement_boost` 0.15. For short queries the planner shifts weights
to `0.30 / 0.40 / 0.30`.

The rank component makes the fusion robust to scale differences; the weighted component keeps the
within-strategy score distance meaningful; the agreement term rewards ids that more than one
strategy found.

### Step 5 — reranking

Reranking runs on the **fused** list, not on the individual strategy lists, and only on the first
`rerank_top_k` entries (default 30). The reranker receives `{query, text}` pairs built from the
document source (`buildRerankerText()`: title/name plus body area), never the raw engine scores.

The blend is currently hardcoded:

```php
$item['score'] = ($item['score'] * 0.4) + ($rerank_score * $original_max * 0.6);
```

`$original_max` rescales the reranker's `[0,1]` output onto the fused score range. A reranker
failure (for example the cross-encoder service being down) is caught, logged as a warning, and the
fused order is returned with `meta['reranked'] = false`. Search never fails because of reranking.

A caller can disable it for one search through the plan (`ranking.use_reranker`); otherwise
`config('search.features.reranker')` decides.

## Response metadata

`AdvancedSearchResult->meta` carries:

| Key | Meaning |
|-----|---------|
| `driver` | active Scout driver |
| `strategies_executed` / `strategies` | how many and which strategies ran |
| `reranked` | whether reranking actually ran (false after a reranker failure) |
| `matching` | resolved text-match decision plus engine degradations |
| `per_strategy` | per-strategy ordered hits (`id`, `score`, `rank`), consumed by `ai:evaluate-retrieval-strategies` |
| `unsupported_driver` | present when the engine cannot orchestrate |

## What the `matching` preference does and does not affect

`strict` / `balanced` / `tolerant` / `auto` resolve to granular `TextMatchOptions` and are passed
**only** to the `keyword` and `hybrid` Scout calls. The `vector` call receives no text-match
options: an embedding has no notion of typo tolerance or token coverage.

The preference therefore never changes ensemble weights, `rrf_k`, `rrf_weight`, `agreement_boost`,
or reranking. Those come from the plan. Preference and plan are two independent axes: the plan
decides *which* strategies and *how to fuse*, the preference decides *how forgiving the lexical
strategy is*.

## Configuration reality check

Consumed at runtime:

| Key | Env | Where it is read |
|-----|-----|------------------|
| `search.vector_search.enabled` | `VECTOR_SEARCH_ENABLED` | `FallbackSearchPlanner`, engines |
| `search.vector_search.dimension` / `search.vector.dimensions` | `VECTOR_DIMENSION` | ES `dense_vector` mapping |
| `search.vector.similarity` | `VECTOR_SIMILARITY` | ES mapping |
| `search.analyzers` | `SEARCH_ANALYZER_IT`, `SEARCH_ANALYZER_EN` | per-locale text mappings |
| `search.features.reranker` | `SEARCH_RERANKER_ENABLED` | `EnsembleSearchService` (plan fallback) |
| `search.reranker.top_k` | `SEARCH_RERANKER_TOP_K` | `EnsembleSearchService` |
| `search.text_matching.*` | — | `TextMatchOptionsResolver` |

Declared but **not read by any code today** (do not document them as working knobs):

| Key | Env | Status |
|-----|-----|--------|
| `search.features.ensemble` | `SEARCH_ENSEMBLE_ENABLED` | never read; fusion always runs when more than one strategy executes |
| `search.reranker.weight` | `SEARCH_RERANKER_WEIGHT` | never read; the blend is the hardcoded 0.4/0.6 above |
| `plan.retry_policy.*` | — | produced by both planners, validated by the AI planner, consumed by nobody |

## Evaluation is an offline loop

`ai:evaluate-application-content` (end-to-end ordering) and `ai:evaluate-retrieval-strategies`
(per-strategy ordering, reranker off and on) score the real pipeline against committed relevance
datasets. **No runtime code reads the produced reports.** The loop is: run the command, read the
metrics, change code or configuration in a reviewed commit, re-run. Committed baselines are
regression gates in CI, not inputs to the algorithm.

See `Modules/AI/docs/rag/MODULE.md` for datasets, metrics and baseline locations.

## Common failure modes

| Symptom | Check |
|---------|-------|
| `strategies_executed = 1` when vectors were expected | digits in the query, `VECTOR_SEARCH_ENABLED`, AI module present, engine kNN support |
| `reranked = false` | reranker service reachable; look for the `Reranker failed` warning |
| `unsupported_driver = true` | Scout driver is not an orchestration-capable `ISearchEngine` |
| Vector results are noise after a model switch | embeddings carry the old `model_key`; run `ai:embeddings:repair --stale` |
| Hybrid ranks worse than keyword alone | measure with `ai:evaluate-retrieval-strategies` before changing weights |

## FAQ prompts for RAG

- How many retrieval strategies run for a query with a number in it?
- Why is vector search inactive even though `VECTOR_SEARCH_ENABLED` is true?
- Does `matching=tolerant` change how results are fused?
- Where is the reranker blend defined?
- Which search configuration keys are declared but unused?
- Do the evaluation reports influence search at runtime?
