<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\RelationManagers;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Casts\ActionEnum;
use Modules\Core\Models\User;
use Modules\Core\Rules\NetworkAddress;
use Modules\Core\Services\ApiTokenService;
use Modules\Core\Support\PermissionName;
use Override;

/**
 * The API tokens of an account in the backoffice: when each was last used, when it expires, what it may do,
 * and a revoke action. Administrators issue the tokens of service accounts here; the personal tokens of an
 * ordinary user are the user's own to create, so the issue action is offered for service accounts only.
 *
 * The plain token is handed to the administrator once, in a modal that lives in the page state of that
 * request: it is not written to the session, to the notifications table or to the log, and only its hash
 * is stored.
 */
final class ApiTokensRelationManager extends RelationManager
{
    #[Override]
    protected static string $relationship = 'tokens';

    #[Override]
    protected static ?string $title = 'API tokens';

    #[Override]
    protected static string|BackedEnum|null $icon = Heroicon::OutlinedKey;

    #[Override]
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return self::mayManageTokensOf($ownerRecord);
    }

    #[Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(static fn (Builder $query): Builder => $query->orderByDesc('id'))
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('abilities')
                    ->badge()
                    ->limitList(3)
                    ->expandableLimitedList(),
                TextColumn::make('allowed_cidrs')
                    ->label('Allowed networks')
                    ->state(static fn (PersonalAccessToken $record): array => self::networks($record))
                    ->badge()
                    ->placeholder('Any address'),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->placeholder('Never'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('Never expires')
                    ->color(static fn (PersonalAccessToken $record): string => $record->expires_at === null ? 'warning' : 'gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                $this->issueAction(),
            ])
            ->recordActions([
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The token stops working immediately. This cannot be undone.')
                    ->action(static function (PersonalAccessToken $record): void {
                        $record->delete();

                        Notification::make()->title('Token revoked')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No API tokens');
    }

    /**
     * Shows the plain token of the one just issued. Opened by the issue action with the token as its
     * argument; there is no way to reach it again.
     */
    public function revealTokenAction(): Action
    {
        return Action::make('revealToken')
            ->modalHeading('Copy the token now')
            ->modalDescription('It is shown only once: only its hash is kept, so it cannot be shown again.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('I have copied it')
            ->schema(static fn (array $arguments): array => [
                Text::make((string) ($arguments['token'] ?? ''))
                    ->copyable()
                    ->fontFamily(FontFamily::Mono),
            ]);
    }

    /**
     * Tokens are credentials: only somebody who may update the account may see or change them.
     */
    private static function mayManageTokensOf(Model $owner): bool
    {
        $actor = Auth::user();

        return $owner instanceof User
            && $actor instanceof Authorizable
            && $actor->can(PermissionName::forModel($owner, ActionEnum::Update->value));
    }

    /**
     * @return list<string>
     */
    private static function networks(PersonalAccessToken $token): array
    {
        $raw = $token->getRawOriginal('allowed_cidrs');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? array_values(array_filter($decoded, is_string(...))) : [];
    }

    private function issueAction(): Action
    {
        $service = resolve(ApiTokenService::class);

        return Action::make('issue')
            ->label('Issue token')
            ->icon(Heroicon::OutlinedPlus)
            ->visible(fn (): bool => $this->getOwnerRecord() instanceof User
                && $this->getOwnerRecord()->isServiceAccount()
                && ! $this->getOwnerRecord()->isSuperAdmin())
            ->authorize(fn (): bool => self::mayManageTokensOf($this->getOwnerRecord()))
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('abilities')
                    ->label('Abilities')
                    ->helperText('The api permissions the account holds through its roles.')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(fn (): array => collect($service->grantableAbilities($this->serviceAccount()))
                        ->mapWithKeys(static fn (string $name): array => [$name => $name])
                        ->all()),
                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->helperText('Leave empty for a token that never expires.')
                    ->native(false)
                    ->minDate(now()),
                TagsInput::make('allowed_cidrs')
                    ->label('Allowed networks')
                    ->helperText('Addresses or CIDR networks the token may be used from. Empty means any address.')
                    ->placeholder('10.0.0.0/8')
                    ->nestedRecursiveRules(['string', new NetworkAddress]),
            ])
            ->action(function (array $data) use ($service): void {
                try {
                    $token = $service->issue(
                        $this->serviceAccount(),
                        (string) $data['name'],
                        array_values($data['abilities'] ?? []),
                        filled($data['expires_at'] ?? null) ? Date::parse($data['expires_at']) : null,
                        array_values($data['allowed_cidrs'] ?? []),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('The token was not issued')
                        ->body($exception->validator->errors()->first())
                        ->danger()
                        ->send();

                    return;
                }

                $this->replaceMountedAction('revealToken', ['token' => $token->plainTextToken]);
            });
    }

    private function serviceAccount(): User
    {
        $owner = $this->getOwnerRecord();

        assert($owner instanceof User);

        return $owner;
    }
}
