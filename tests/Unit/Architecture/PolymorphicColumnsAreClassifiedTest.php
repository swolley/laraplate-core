<?php

declare(strict_types=1);

use Modules\CMS\Helpers\HasTags;
use Modules\Core\Listeners\RemoveOriginOfDeletedRecord;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Observers\UserObserver;
use Modules\Core\Search\Traits\RemovesEmbeddingsWithRecord;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A polymorphic column points at a record with no foreign key to follow it, so nothing removes its rows when
 * that record is deleted for good unless some code does. Each one is classified here (decision D49 of the
 * revision-centric history review): a child, with the code that removes it at a hard delete; a reference that
 * is kept on purpose; the version history, which the hard-delete coordinator handles; or a transient log.
 * A new polymorphic column fails this test until it is classified.
 *
 * Keys are the migration file name without its date prefix, a colon and the column prefix.
 *
 * @return array<string, array{0: 'child'|'reference'|'history'|'transient', 1?: string}>
 */
function polymorphic_column_classes(): array
{
    return [
        'create_taggables_table:taggable' => ['child', HasTags::class],
        'create_model_embeddings_table:model' => ['child', RemovesEmbeddingsWithRecord::class],
        'create_modifications_table:modifiable' => ['child', HasApprovals::class],
        'create_media_table:model' => ['child', InteractsWithMedia::class],
        'create_record_origins_table:referable' => ['child', RemoveOriginOfDeletedRecord::class],
        'create_notifications_table:notifiable' => ['child', UserObserver::class],
        'create_personal_access_tokens_table:tokenable' => ['child', UserObserver::class],
        // Who asked for a change and who voted on it: the audit of the approval, kept with the voter shown as deleted.
        'create_modifications_table:modifier' => ['reference'],
        'create_approvals_table:approver' => ['reference'],
        'create_disapprovals_table:disapprover' => ['reference'],
        'deduplicate_approval_votes:approver' => ['reference'],
        'deduplicate_approval_votes:disapprover' => ['reference'],
        // The ERP documents that point at their source document belong to the referencing document.
        'create_journal_entries_table:reference' => ['reference'],
        'create_stock_movements_table:source' => ['reference'],
        'create_stock_reservations_table:source' => ['reference'],
        'create_version_sets_table:root' => ['history'],
        'create_versions_table:versionable' => ['history'],
        'create_outbox_events_table:aggregate' => ['transient'],
        'create_outbox_events_table:event' => ['transient'],
    ];
}

/**
 * The polymorphic columns declared by the migrations: `morphs()` and its variants, and a pair of `{prefix}_type`
 * and `{prefix}_id` columns.
 *
 * @return list<string>
 */
function polymorphic_columns_in_migrations(string $root): array
{
    $found = [];
    $files = [...glob("{$root}/Modules/*/database/migrations/*.php") ?: [], ...glob("{$root}/database/migrations/*.php") ?: []];

    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        $name = (string) preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($file, '.php'));
        $prefixes = [];

        preg_match_all("/(?:nullable|uuid|ulid|nullableUuid|nullableUlid)?[mM]orphs\(\s*'([a-z_]+)'/", $source, $morphs);
        preg_match_all("/'([a-z_]+)_type'/", $source, $types);

        foreach ($morphs[1] as $prefix) {
            $prefixes[] = $prefix;
        }

        foreach ($types[1] as $prefix) {
            if (str_contains($source, "'{$prefix}_id'")) {
                $prefixes[] = $prefix;
            }
        }

        foreach (array_unique($prefixes) as $prefix) {
            $found[] = "{$name}:{$prefix}";
        }
    }

    sort($found);

    return $found;
}

it('classifies every polymorphic column of the schema', function (): void {
    $found = polymorphic_columns_in_migrations(dirname(__DIR__, 5));

    expect(array_values(array_diff($found, array_keys(polymorphic_column_classes()))))->toBe([]);
});

it('names code that exists for every child it lists', function (): void {
    foreach (polymorphic_column_classes() as $column => $class) {
        if ($class[0] === 'child') {
            expect(class_exists($class[1]) || trait_exists($class[1]))->toBeTrue("{$column}: {$class[1]}");
        }
    }
});

it('lists no column that the migrations no longer declare', function (): void {
    expect(array_values(array_diff(array_keys(polymorphic_column_classes()), polymorphic_columns_in_migrations(dirname(__DIR__, 5)))))->toBe([]);
});
