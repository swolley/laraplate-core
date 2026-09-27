<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications;

use BackedEnum;
use Coolsam\Modules\Resource;
use Filament\Panel;
// use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Resources\Modifications\Pages\ListModifications;
use Modules\Core\Filament\Resources\Modifications\Schemas\ModificationForm;
use Modules\Core\Filament\Resources\Modifications\Tables\ModificationsTable;
use Modules\Core\Models\Concerns\HasApprovals;
use Modules\Core\Models\Modification;
use Override;
use UnitEnum;

final class ModificationResource extends Resource
{
    #[Override]
    protected static ?string $model = Modification::class;

    #[Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    #[Override]
    protected static string|UnitEnum|null $navigationGroup = 'Core';

    #[Override]
    protected static ?int $navigationSort = 6;

    /**
     * Modifications only exist when some active model goes through approvals.
     */
    #[Override]
    public static function canAccess(): bool
    {
        return self::hasApprovableModels() && parent::canAccess();
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'core/modifications';
    }

    public static function form(Schema $schema): Schema
    {
        return ModificationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModificationsTable::configure($table)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['modifier', 'approvals', 'disapprovals']));
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModifications::route('/'),
        ];
    }

    /**
     * Modifications are written by the approval flow: the panel only votes on them.
     */
    #[Override]
    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny('Modifications are created by the approval flow.');
    }

    #[Override]
    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Modifications can only be approved or disapproved.');
    }

    #[Override]
    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Modifications can only be approved or disapproved.');
    }

    #[Override]
    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Modifications can only be approved or disapproved.');
    }

    #[Override]
    public static function getForceDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny('Modifications can only be approved or disapproved.');
    }

    #[Override]
    public static function getForceDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny('Modifications can only be approved or disapproved.');
    }

    private static function hasApprovableModels(): bool
    {
        return once(static fn (): bool => models(
            filter: static fn (string $model): bool => class_uses_trait($model, HasApprovals::class),
        ) !== []);
    }
}
