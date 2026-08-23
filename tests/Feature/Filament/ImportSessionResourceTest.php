<?php

declare(strict_types=1);

use Modules\Core\Filament\Resources\ImportSessions\ImportSessionResource;
use Modules\Core\Models\ImportSession;

test('the import session resource is bound to its model under the Core group', function (): void {
    expect(ImportSessionResource::getModel())->toBe(ImportSession::class)
        ->and(ImportSessionResource::getNavigationGroup())->toBe('Core')
        ->and(ImportSessionResource::getSlug())->toStartWith('core/');
});

test('the import session resource is a monitoring surface: list and view, no create', function (): void {
    expect(array_keys(ImportSessionResource::getPages()))->toBe(['index', 'view'])
        ->and(ImportSessionResource::canCreate())->toBeFalse();
});

test('the table wires a run action and a failure-report download through the shared launcher', function (): void {
    $table = (string) file_get_contents(
        dirname(__DIR__, 3) . '/app/Filament/Resources/ImportSessions/Tables/ImportSessionsTable.php',
    );

    expect($table)->toContain("Action::make('run')")
        ->and($table)->toContain('ImportLauncher')
        ->and($table)->toContain("Action::make('downloadErrors')")
        ->and($table)->toContain('failed_rows > 0');
});
