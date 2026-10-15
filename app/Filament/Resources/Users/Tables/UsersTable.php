<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Password;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Role;

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
                        ->state(static fn (User $record): array => $record->roles
                            ->map(static fn (Role $role): string => $role->name . ' · ' . $role->guard_name)
                            ->all())
                        ->toggleable(),
                    IconColumn::make('is_service_account')
                        ->label('Service account')
                        ->boolean()
                        ->falseIcon(false)
                        ->toggleable(),
                ]);

                return $default_columns;
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->unshift(
                    Action::make('resend_verification_email')
                        ->label('Resend Verification Email')
                        ->icon('heroicon-o-envelope')
                        ->authorize(static fn (User $record): bool => ! $record->isServiceAccount() && ! $record->hasVerifiedEmail())
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
                        ->authorize(static fn (User $record): bool => ! $record->isServiceAccount())
                        ->action(static function (User $record): void {
                            $status = Password::broker()->sendResetLink(['email' => $record->email]);

                            if ($status !== Password::RESET_LINK_SENT) {
                                Notification::make()
                                    ->title(__($status))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Password reset email has been sent.')
                                ->send();
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
                    TernaryFilter::make('is_service_account')
                        ->label('Service account'),
                    TernaryFilter::make('verified')
                        ->label('Verified email')
                        ->attribute('email_verified_at')
                        ->nullable(),
                ]);
            },
        );
    }
}
