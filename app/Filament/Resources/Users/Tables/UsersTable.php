<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\User;

final class UsersTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): Collection {
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
            actions: static function (Collection $default_actions): void {
                $default_actions->unshift(
                    Action::make('resend_verification_email')
                        ->label('Resend Verification Email')
                        ->icon('heroicon-o-envelope')
                        ->authorize(static fn (User $record): bool => ! $record->hasVerifiedEmail())
                        ->action(static function (User $record): void {
                            $notification = new VerifyEmail();
                            $notification->url = filament()->getVerifyEmailUrl($record);

                            $record->notify($notification);
                            Notification::make()
                                ->title('Verification email has been resent.')
                                ->send();
                        })
                        ->requiresConfirmation(),
                );
                $default_actions->unshift(
                    Action::make('reset_password')
                        ->label('Reset Password')
                        ->icon('heroicon-o-key')
                        ->authorize(fn (User $record): bool => true)
                        ->action(function (User $record): void {
                            $record->sendPasswordResetNotification($record->email);
                        })
                        ->requiresConfirmation(),
                );
            },
            filters: static function (Collection $default_filters): void {
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
