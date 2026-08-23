<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Support\Import;

use Modules\Core\Import\Contracts\EntityImporterInterface;
use Modules\Core\Import\Enums\ImportRowOutcome;
use Modules\Core\Import\Exceptions\RowImportException;
use Modules\Core\Import\Support\ImportRowContext;
use Modules\Core\Import\ValueObjects\ImportField;
use Override;
use RuntimeException;

/**
 * A minimal importer over {@see StubWidget} for framework tests. It upserts by
 * `code`, raises a {@see RowImportException} on a blank code, and — for a code
 * starting with `boom` — persists the widget and then throws, so a test can prove
 * the per-row savepoint rolls that write back.
 */
final class StubWidgetImporter implements EntityImporterInterface
{
    #[Override]
    public function key(): string
    {
        return 'stub.widget';
    }

    #[Override]
    public function label(): string
    {
        return 'Widgets';
    }

    /**
     * @return list<ImportField>
     */
    #[Override]
    public function fields(): array
    {
        return [
            new ImportField('code', 'Code', required: true),
            new ImportField('name', 'Name', aliases: ['title']),
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    #[Override]
    public function import(array $row, ImportRowContext $context): ImportRowOutcome
    {
        $code = mb_trim($row['code'] ?? '');

        if ($code === '') {
            throw RowImportException::withErrors(['code' => ['The code is required.']]);
        }

        $existing = StubWidget::query()->where('code', $code)->first();
        $widget = $existing ?? new StubWidget(['code' => $code]);
        $widget->name = $row['name'] ?? '';
        $widget->save();

        if (str_starts_with($code, 'boom')) {
            throw new RuntimeException('Kaboom after write.');
        }

        return $existing instanceof StubWidget ? ImportRowOutcome::Updated : ImportRowOutcome::Created;
    }
}
