<?php

namespace Modules\Core\Filament\Resources\Users\Tables;

use \Override;
use Filament\Actions\Action;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\BaseTable;
use Modules\Core\Models\User;

class UsersTable extends BaseTable
{
    #[Override]
    protected function getModel(): string
    {
        return User::class;
    }

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: function (Collection $default_columns) {
                $default_columns->unshift(...[
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('username')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('email')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('roles.name')
                        ->badge()
                        ->toggleable(),
                ]);

                return $default_columns;
            },
            actions: function (Collection $default_actions) {
                $default_actions->unshift(
                    Action::make('resend_verification_email')
                        ->label('Resend Verification Email')
                        ->icon('heroicon-o-envelope')
                        ->authorize(fn(User $record) => !$record->hasVerifiedEmail())
                        ->action(function (User $record) {
                            $notification = new VerifyEmail();
                            $notification->url = filament()->getVerifyEmailUrl($record);
                            $record->notify($notification);
                            Notification::make()
                                ->title("Verification email has been resent.")
                                ->send();
                        })
                        ->requiresConfirmation()
                );
            },
            filters: function (Collection $default_filters) {
                $default_filters->unshift(...[
                    SelectFilter::make('roles')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload(),
                    TernaryFilter::make('verified')
                        ->label('Verified email')
                        ->attribute('email_verified_at')
                        ->nullable(),
                ]);
            },
        );
    }
}
