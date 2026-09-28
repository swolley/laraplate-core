<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Resources\Modifications\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Modules\CMS\Models\Comment;
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Services\ModificationVoteService;

final class ModificationsTable
{
    use HasTable;

    public static function configure(Table $table): Table
    {
        return self::configureTable(
            table: $table,
            columns: static function (Collection $default_columns): void {
                $default_columns->unshift(...[
                    TextColumn::make('modifiable_id')
                        ->numeric()
                        ->sortable()
                        ->searchable(),
                    TextColumn::make('modifiable_type')
                        ->searchable(),
                    TextColumn::make('operation')
                        ->badge()
                        ->sortable(),
                    TextColumn::make('modifier.name')
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('modifications.original')
                        ->label('Original')
                        ->limit(50),
                    TextColumn::make('modifications.modified')
                        ->label('Modified')
                        ->limit(50),
                    TextColumn::make('meta')
                        ->label('Meta')
                        ->badge()
                        ->getStateUsing(function (Modification $record): ?string {
                            $meta = $record->latestAutomatedVoteMeta();

                            $string = '';

                            foreach ($meta as $key => $value) {
                                $string .= $key . ': ' . $value . '<br>';
                            }

                            return $string;
                        })
                        ->color(fn (?string $state): string => match ($state) {
                            'requires_human_review' => 'warning',
                            'processing', 'queued' => 'gray',
                            'auto_approved' => 'success',
                            'auto_rejected' => 'danger',
                            default => 'gray',
                        })
                        ->visible(fn (?Modification $record): bool => $record === null || $record->modifiable_type === Comment::class)
                        ->html(),
                    TextColumn::make('disapprovers_required')
                        ->label('Disapprovals required')
                        ->numeric()
                        ->visible(fn (?Modification $record): bool => $record === null || $record->modifiable_type === Comment::class),
                ]);
            },
            actions: static function (Collection $default_actions): void {
                $default_actions->push(
                    self::voteAction(approval: true),
                    self::voteAction(approval: false),
                    self::withdrawAction(),
                );
            },
            fixedActions: ['approve', 'disapprove', 'withdraw'],
        )
            ->defaultGroup(
                Group::make('modifiable_type')
                    ->label('Modifiable Type'),
            );
    }

    /**
     * Withdraw a pending request, available only to its author until it is decided.
     */
    public static function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label('Withdraw')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(static function (Modification $record): bool {
                $user = Auth::user();

                return $record->active
                    && $user instanceof User
                    && $record->modifier_type === $user::class
                    && (string) $record->modifier_id === (string) $user->getKey();
            })
            ->action(static function (Modification $record): void {
                $user = Auth::user();
                throw_unless($user instanceof User, LogicException::class, 'Authenticated user is required.');

                resolve(ModificationVoteService::class)->withdraw($user, $record);

                Notification::make()
                    ->title('Request withdrawn')
                    ->success()
                    ->send();
            });
    }

    /**
     * Approve or disapprove a pending modification, available only to users allowed to vote on it.
     */
    public static function voteAction(bool $approval): Action
    {
        return Action::make($approval ? 'approve' : 'disapprove')
            ->label($approval ? 'Approve' : 'Disapprove')
            ->icon($approval ? Heroicon::OutlinedHandThumbUp : Heroicon::OutlinedHandThumbDown)
            ->color($approval ? 'success' : 'danger')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->maxLength(255),
            ])
            ->visible(static function (Modification $record) use ($approval): bool {
                $user = Auth::user();

                return $user instanceof User && resolve(ModificationVoteService::class)->canVote($user, $record, $approval);
            })
            ->action(static function (Modification $record, array $data) use ($approval): void {
                $user = Auth::user();
                throw_unless($user instanceof User, LogicException::class, 'Authenticated user is required.');

                $reason = $data['reason'] ?? null;

                $record->getConnection()->transaction(static function () use ($record, $user, $approval, $reason): void {
                    /** @var Modification $locked */
                    $locked = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->sole();

                    resolve(ModificationVoteService::class)->cast(
                        $user,
                        $locked,
                        $approval,
                        is_string($reason) && $reason !== '' ? $reason : null,
                    );
                });

                Notification::make()
                    ->title($approval ? 'Approval recorded' : 'Disapproval recorded')
                    ->success()
                    ->send();
            });
    }
}
