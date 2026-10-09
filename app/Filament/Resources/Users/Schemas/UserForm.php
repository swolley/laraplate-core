<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Users\Schemas;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Utils\HasForm;

final class UserForm
{
    use HasForm;

    public static function configure(Schema $schema): Schema
    {
        self::configureForm($schema);

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('username')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('is_service_account')
                    ->label('Service account')
                    ->helperText('Non-interactive account: no password, no sign-in, no backoffice. It authenticates with API tokens issued here, within the api permissions of its roles.')
                    ->live()
                    ->rules([
                        static fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value !== true) {
                                return;
                            }

                            $guest = config('permission.users.guest');

                            if (is_string($guest) && $guest !== '' && in_array($guest, [$get('name'), $get('username')], true)) {
                                $fail('The anonymous account, and any account named like it, cannot be a service account.');
                            }
                        },
                    ]),
                TextInput::make('password')
                    ->password()
                    ->required(static fn (Get $get): bool => ! $get('is_service_account'))
                    ->minLength(8)
                    ->hidden(static fn (Get $get, string $operation): bool => $operation === 'edit' || (bool) $get('is_service_account')),
                Select::make('lang')
                    ->options(translations())
                    ->nullable(),
                Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload(),
            ]);
    }
}
