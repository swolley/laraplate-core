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
     TextMatchOptionsResolver::resolve() -> ResolvedTextMatch (lexical only, carries the query analysis)
     RetrievalTuningProfile::apply()    -> measured profile per QueryClass, only when search.adaptive_tuning is on
  3. resolveVector()                    -> query embedding, or null
  4. EnsembleSearchService::search()
       executeScoutSearch() x1 or x3    -> keyword | vector | hybrid, SEQUENTIAL
       RankFusion::fuse()               -> weighted normalized score + RRF + agreement
  5. rerankTopK()                       -> IReranker over the fused top-K
```

### Step 2 — the plan decides how many strategies run

`FallbackSearchPlanner::fallbackPlan()` (Core, no LLM) applies two rules:

```php
$is_short   = mb_strlen($query) < 20;
$has_numbers = (bool) preg_match('/\d/', $query);
$use_vector  = config('core.search.vector.enabled', false) && ! $has_numbers;
```

| Condition | Strategies executed |
|-----------|---------------------|
| `core.search.vector.enabled` = false | `keyword` only |
| query contains any digit | `keyword` only |
| engine without `supportsOrchestratedVectorSearch()` | `keyword` only (plan downgraded by `applyEngineCapabilities()`) |
| `ITextEmbedder` not bound (AI module absent or search orchestration disabled) | `keyword` only (`$vector === null`) |
| otherwise | `keyword` + `vector` + `hybrid` |

**One or three, never two**: `hybrid` runs only when both `use_fulltext` and `use_vector` are true.
The three Scout calls are **sequential**, not parallel. When a strategy does not run, its weight is
removed and the remaining weights are renormalized to sum to 1.0
(`RankFusion::renormalizeWeights()`), so a keyword-only run gives keyword weight 1.0 and
fusion becomes a pass-through.

`SearchOrchestratorAgent` (AI module) can replace the planner with an LLM-produced plan; it is
clamped and validated before use, and falls back to the same rule-based plan on any failure.

### Step 2b — the retrieval tuning profile (L1)

When the `search.adaptive_tuning` runtime setting (`core.search.adaptive_tuning`, group `search`,
seeded **off**) is on, `RetrievalTuningProfile::apply()` merges a committed parameter set into the
plan right after the planner and the capability downgrade, so it covers both planners. It is not
learning: nothing reads user behaviour and two identical requests get identical plans.

The query class comes from the analysis `TextMatchOptionsResolver` already made (no second
tokenizer), via `QueryClass::fromAnalysis()`, in this precedence:

| Class | Condition |
|-------|-----------|
| `identifier` | a significant numeric, UUID, email or structured-identifier token (`INV-1042`) |
| `short_keyword` | at most two significant tokens |
| `multi_term` | three to five significant tokens and fewer than two stopwords |
| `natural_language` | anything longer, or two stopwords or more |

Short words and acronyms are protected from fuzziness by text matching but are not codes, so they
do not make a query an `identifier`.

The profile is `Modules/Core/config/search_tuning.php` (`config('search_tuning')`): a `version`, a
`default` set and one optional set per class. The class set overrides `default`; a parameter left
out keeps the planner value. Allowed parameters: `keyword_weight`, `vector_weight`,
`hybrid_weight`, `rrf_weight`, `agreement_boost`, `rerank_blend` (numbers in `[0, 1]`), `rrf_k` and
`rerank_top_k` (integers >= 1); a set defining all three weights must keep one positive. The
profile only writes `plan.ensemble` and `plan.ranking`; it never touches `plan.retrieval`, so
whether vector search runs stays a capability question.

With the switch off, or a missing or invalid profile, the plan is returned untouched (an invalid
profile logs one warning per process). The shipped profile restates the L0 constants
(`rrf_k` 60, `rrf_weight` 0.25, `agreement_boost` 0.15, empty class sets), so switching it on
changes only `meta['tuning']` until a measured profile is committed. Profile values come from
`ai:tune-retrieval` (AI module), never from intuition: see
`Modules/AI/docs/rag/MODULE.md`.

### Step 3 — the query vector is an AI-module capability

```php
if (($retrieval['use_vector'] ?? false) !== true || ! $this->app->bound(ITextEmbedder::class)) {
    return null;
}
```

`ITextEmbedder` is bound **only** by `AIServiceProvider`, and only when
`ai.features.search_orchestration.enabled` is true. Without the AI module the interface is unbound,
the query vector is `null`, and the pipeline stays keyword-only. This is independent of
`core.search.vector.enabled`: both must hold.

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

The math lives in `RankFusion` (pure, no engine or container), which `EnsembleSearchService`
delegates to and the offline tuner reuses to re-fuse recorded rankings.

Per strategy, hit scores are min-max normalized to `[0,1]` (`RankFusion::minMaxNormalize()`), because BM25
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

The blend comes from the plan (`ranking.rerank_blend`, set by the tuning profile when it defines
one), falling back to the `search.reranker.weight` runtime setting (`core.search.reranker.weight`,
seeded `0.6`):

```php
$item['score'] = ($item['score'] * (1 - $blend)) + ($rerank_score * $original_max * $blend);
```

The seeded `0.6` reproduces the historical `0.4 / 0.6` split exactly. `0` keeps the fused order,
`1` ranks the top-K by the reranker alone. `$original_max` rescales the reranker's `[0,1]` output onto the fused score range. A reranker
failure (for example the cross-encoder service being down) is caught, logged as a warning, and the
fused order is returned with `meta['reranked'] = false`. Search never fails because of reranking.

A caller can disable it for one search through the plan (`ranking.use_reranker`); otherwise
`config('core.search.reranker.enabled')` decides.

## Response metadata

`AdvancedSearchResult->meta` carries:

| Key | Meaning |
|-----|---------|
| `driver` | active Scout driver |
| `strategies_executed` / `strategies` | how many and which strategies ran |
| `reranked` | whether reranking actually ran (false after a reranker failure) |
| `matching` | resolved text-match decision plus engine degradations |
| `per_strategy` | per-strategy ordered hits (`id`, `score`, `rank`), consumed by `ai:evaluate-retrieval-strategies` and `ai:tune-retrieval` |
| `tuning` | present only when the tuning profile was applied: `{applied, profile_version, query_class}` |
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
| `core.search.vector.enabled` | runtime setting (Filament > Settings) | `FallbackSearchPlanner`, engines |
| `core.search.vector.dimensions` | runtime setting | ES `dense_vector` mapping |
| `core.search.vector.similarity` | runtime setting | ES mapping |
| `search.analyzers` | `SEARCH_ANALYZER_IT`, `SEARCH_ANALYZER_EN` | per-locale text mappings |
| `core.search.reranker.enabled` | runtime setting | `EnsembleSearchService` (plan fallback) |
| `core.search.reranker.top_k` | runtime setting | `EnsembleSearchService` |
| `core.search.reranker.weight` | runtime setting | `EnsembleSearchService` (rerank blend when the plan sets none) |
| `core.search.adaptive_tuning` | runtime setting | `RetrievalTuningProfile` (switch) |
| `search_tuning.*` | — (committed file) | `RetrievalTuningProfile`, `ai:tune-retrieval` |
| `search.text_matching.*` | — | `TextMatchOptionsResolver` |

Declared but **not read by any code today** (do not document them as working knobs):

| Key | Env | Status |
|-----|-----|--------|
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
| `strategies_executed = 1` when vectors were expected | digits in the query, `core.search.vector.enabled`, AI module present, engine kNN support |
| `reranked = false` | reranker service reachable; look for the `Reranker failed` warning |
| `unsupported_driver = true` | Scout driver is not an orchestration-capable `ISearchEngine` |
| Vector results are noise after a model switch | embeddings carry the old `model_key`; run `ai:embeddings:repair --stale` |
| Hybrid ranks worse than keyword alone | measure with `ai:evaluate-retrieval-strategies` before changing weights |
| Ranking changed after flipping `search.adaptive_tuning` | read `meta['tuning']` (profile version, query class); switch it off to return to L0 without a deploy |

## FAQ prompts for RAG

- How many retrieval strategies run for a query with a number in it?
- Why is vector search inactive even though `core.search.vector.enabled` is true?
- Does `matching=tolerant` change how results are fused?
- Where is the reranker blend defined?
- What does the `search.adaptive_tuning` setting change, and how do I tell from a response?
- Which query class does a query with an invoice code fall into?
- Which search configuration keys are declared but unused?
- Do the evaluation reports influence search at runtime?
