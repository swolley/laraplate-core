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
use Modules\Core\Filament\Utils\HasTable;
use Modules\Core\Models\Modification;
use Modules\Core\Models\User;
use Modules\Core\Services\ModerationAdapterRegistry;
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
                        ->label('AI moderation')
                        ->badge()
                        // The automated moderator's decision, from the latest automated vote:
                        // auto_approved, auto_rejected or requires_human_review; empty when none voted.
                        ->getStateUsing(static fn (Modification $record): ?string => ($status = $record->latestAutomatedVoteMeta()['status'] ?? null) === null ? null : (string) $status)
                        ->tooltip(static function (Modification $record): ?string {
                            $meta = $record->latestAutomatedVoteMeta();

                            if ($meta === null) {
                                return null;
                            }

                            return collect(['verdict', 'confidence', 'reason'])
                                ->filter(static fn (string $key): bool => isset($meta[$key]) && is_scalar($meta[$key]))
                                ->map(static fn (string $key): string => $key . ': ' . $meta[$key])
                                ->implode(' · ');
                        })
                        ->color(fn (?string $state): string => match ($state) {
                            'requires_human_review' => 'warning',
                            'processing', 'queued' => 'gray',
                            'auto_approved' => 'success',
                            'auto_rejected' => 'danger',
                            default => 'gray',
                        })
                        ->visible(fn (?Modification $record): bool => self::isAutomaticallyModerated($record)),
                    TextColumn::make('disapprovers_required')
                        ->label('Disapprovals required')
                        ->numeric()
                        ->visible(fn (?Modification $record): bool => self::isAutomaticallyModerated($record)),
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

    /**
     * Whether the record's model has a registered moderation adapter, so automated moderation can
     * have voted on it. A column evaluated without a record stays visible.
     */
    private static function isAutomaticallyModerated(?Modification $record): bool
    {
        return $record === null
            || in_array($record->modifiable_type, app(ModerationAdapterRegistry::class)->modelClasses(), true);
    }
}
