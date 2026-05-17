# Event orchestration — search indexing and modification moderation

## Purpose

Core is the **event bus** between domain modules (CMS, ERP, …) and optional AI capabilities. Modules do not import each other for these pipelines; they use Core events, registries, and per-model settings.

This document covers two orchestration patterns:

1. **Search indexing** — `ModelRequiresIndexing` (embeddings + optional translation → Elasticsearch/Typesense)
2. **Modification moderation** — `ModificationRequiresModeration` (optional AI vote on pending approvals)

Full technical detail (extra diagrams): `Modules/Core/docs/EVENT_ORCHESTRATION.md`.

## Capabilities

| Pattern | Core event | AI pre-processing keys | Core fallback when AI skips |
|---------|------------|------------------------|-----------------------------|
| Search indexing | `ModelRequiresIndexing` | `embeddings`, `translation` | **Runs** `IndexInSearchJob` |
| Modification moderation | `ModificationRequiresModeration` | `ai_approval` | **No-op** (humans only) |

## Design principles

- Core owns events, cache coordination, finalize/fallback listeners.
- Domain modules register adapters (e.g. CMS `CommentModerationAdapter` on `ModerationAdapterRegistry`).
- AI never imports CMS/ERP; it resolves context via the registry.
- Opt-in per model: `ai_moderation_{table}`, `auto_translate_{table}`; search via `Searchable` + `$embed`.

## InternalFlow — search indexing

### Trigger

Models with `Modules\Core\Search\Traits\Searchable` call `queueMakeSearchable()` / `makeSearchable()` → `ModelRequiresIndexing`.

### Main classes

| Layer | Class | Role |
|-------|-------|------|
| Core | `Events\ModelRequiresIndexing` | Orchestration state |
| Core | `Events\ModelPreProcessingCompleted` | One step done (`embeddings`, `translation`) |
| Core | `Listeners\IndexModelFallbackListener` | Index without AI when `!handled` |
| Core | `Listeners\FinalizeModelIndexingListener` | Dispatches `IndexInSearchJob` when all steps complete |
| Core | `Search\Jobs\IndexInSearchJob` | Writes to Scout engine |
| AI | `Listeners\HandleModelIndexingListener` | Registers embeddings, dispatches `GenerateEmbeddingsJob` |
| AI | `Jobs\GenerateEmbeddingsJob` | Persists vectors |
| AI | `Listeners\HandleModelTranslationListener` | `TranslatedModelSaved` → `TranslateModelJob` |

### Cache

| Key | Pre-processing |
|-----|----------------|
| `model_indexing:{table}:{id}` | `embeddings`, `translation` |

### Configuration

| Layer | Keys |
|-------|------|
| Scout | `SCOUT_DRIVER`, `VECTOR_SEARCH_ENABLED`, model `$embed` |
| AI | `ai.features.embeddings.enabled` |
| Per model | `auto_translate_{table}` via `PerModelSettingResolver` |

## InternalFlow — modification moderation

### Trigger

When an **active** `Modification` is **created** (`wasRecentlyCreated`), Core emits `ModificationRequiresModeration` (emitter in `Core\Providers\EventServiceProvider`). Pending comments may fire before `modifiable_id` is set; builders read context from modification JSON.

### Main classes

| Layer | Class | Role |
|-------|-------|------|
| Core | `Events\ModificationRequiresModeration` | Same orchestration pattern as indexing |
| Core | `Events\ModificationApproved` | After `applyModificationChanges()` |
| Core | `Events\ModificationPreProcessingCompleted` | e.g. `ai_approval` done |
| Core | `Contracts\ModerationAdapter` | Domain adapter (returns `ModerationRequest`) |
| Core | `Services\ModerationAdapterRegistry` | Resolves adapter by modification |
| Core | `Data\ModerationInput` / `ModerationRequest` | Neutral input + domain-owned prompts |
| Core | `Listeners\ModificationModerationFallbackListener` | No-op if `!handled` |
| Core | `Services\PerModelSettingResolver` | `ai_moderation_*`, `auto_translate_*` |
| AI | `Listeners\HandleModificationModerationListener` | Dispatches `ApproveModificationJob` |
| AI | `Jobs\ApproveModificationJob` | LLM vote → `approvals` / `disapprovals` `meta` |
| CMS | `CommentModerationAdapter` + `CommentModerationPrompt` | Article, optional parent comment, prompts |

### Cache

| Key | Purpose |
|-----|---------|
| `modification_moderation:{modification_id}` | Cached event until pre-processing completes |

### Configuration

| Layer | Keys |
|-------|------|
| AI global | `ai.features.moderation.*` (`AI_MODERATION_*` env) |
| Per model | Setting `ai_moderation_{table}` (group `moderation`) |
| System actor | `ai.features.moderation.system_user_id` (`AI_MODERATOR_USER_ID`) |

### Post-approval

`Comment::applyModificationChanges()` emits `ModificationApproved` → AI `HandleModificationApprovedTranslationListener` may dispatch `TranslateModelJob` when `auto_translate_*` is enabled.

## HowToUse — extend

### New searchable model

1. Add `Searchable`, implement `toSearchableArray()` / `$embed`.
2. Enable `ai.features.embeddings.enabled` and provider.
3. Optionally `auto_translate_{table}`.

### New moderatable model

1. Implement `ModerationAdapter` in the domain module (build `ModerationRequest` with domain prompts).
2. Register on `ModerationAdapterRegistry` in module `ServiceProvider`.
3. Enable `ai_moderation_{table}` for models using `HasApprovals`.
4. No AI module code changes required.

## ErrorsAndTroubleshooting

| Symptom | Check |
|---------|--------|
| Model never indexed | `Searchable`, Scout driver, `IndexModelFallbackListener` registered |
| Embeddings missing | `ai.features.embeddings.enabled`, model `$embed`, `GenerateEmbeddingsJob` queue |
| AI moderation never runs | `ai.features.moderation.enabled`, `system_user_id`, registry builder, `ai_moderation_{table}` |
| Comment pending, no AI | `CommentModerationContextBuilder` registered in `CMSServiceProvider` |
| Humans only (expected) | AI skipped → fallback is no-op; Filament approval still works |

## FAQPrompts

- How does ModelRequiresIndexing work with embeddings and translation?
- What is ModificationRequiresModeration and who emits it?
- Difference between search indexing fallback and moderation fallback?
- How do I add AI moderation for a new model type?
- What settings control ai_moderation and auto_translate per table?
- Where does CMS register CommentModerationContextBuilder?
