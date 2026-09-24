<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transfers\Tables;

use App\Actions\Transfers\AssignTransferReview;
use App\Actions\Transfers\CommentOnTransfer;
use App\Actions\Transfers\MatchTransfer;
use App\Actions\Transfers\RecordFinalReview;
use App\Models\Transfer;
use App\Models\User;
use App\PermissionKey;
use App\Rules\NoBankAccountInText;
use App\Services\ReceiptStorage;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * قائمة الحوالات في اللوحة: الإيصال، علامة المتكررة، والمراجعة الاختيارية (FR-43..47).
 */
class TransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user:id,full_name', 'beneficiary:id,display_name', 'assignee:id,full_name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('beneficiary.display_name')
                    ->label(__('transfers.fields.beneficiary_id'))
                    ->wrap(),
                TextColumn::make('user.full_name')
                    ->label(__('transfers.admin.initiator'))
                    ->wrap(),
                TextColumn::make('amount')
                    ->label(__('transfers.fields.amount'))
                    ->numeric(decimalPlaces: 2, locale: 'en'),
                TextColumn::make('transferred_on')
                    ->label(__('transfers.fields.transferred_on'))
                    ->date('Y-m-d'),
                IconColumn::make('is_repeated')
                    ->label(__('transfers.review.repeated'))
                    ->boolean(),
                TextColumn::make('review_state')
                    ->label(__('transfers.review.state'))
                    ->badge()
                    ->formatStateUsing(fn (Transfer $record): string => __('transfers.review.states.'.$record->review_state->value)),
                TextColumn::make('assignee.full_name')
                    ->label(__('transfers.review.assignee'))
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label(__('transfers.review.receipt'))
                    ->url(fn (Transfer $record): string => app(ReceiptStorage::class)->temporaryUrl($record))
                    ->openUrlInNewTab(),
                Action::make('match')
                    ->label(__('transfers.review.match'))
                    ->visible(fn (Transfer $record): bool => self::actor()->can('match', $record))
                    ->action(function (Transfer $record): void {
                        app(MatchTransfer::class)->handle(self::actor(), $record);
                        Notification::make()->title(__('transfers.review.matched'))->success()->send();
                    }),
                Action::make('assign')
                    ->label(__('transfers.review.assign'))
                    ->visible(fn (Transfer $record): bool => self::actor()->can(PermissionKey::TransfersAssign->value) && $record->comments()->doesntExist() && $record->final_reviewed_at === null)
                    ->schema([
                        Select::make('assignee_id')
                            ->label(__('transfers.review.assignee'))
                            ->options(fn (): array => self::reviewers()->pluck('full_name', 'id')->all())
                            ->required(),
                    ])
                    ->action(function (Transfer $record, array $data): void {
                        $assignee = User::query()->whereKey($data['assignee_id'])->firstOrFail();
                        app(AssignTransferReview::class)->handle(self::actor(), $record, $assignee);
                        Notification::make()->title(__('transfers.review.assigned'))->success()->send();
                    }),
                Action::make('comment')
                    ->label(__('transfers.review.comment'))
                    ->visible(fn (Transfer $record): bool => self::actor()->can('comment', $record))
                    ->schema([
                        Textarea::make('body')
                            ->label(__('transfers.review.comment_body'))
                            ->required()
                            ->maxLength(2000)
                            ->rule(new NoBankAccountInText),
                    ])
                    ->action(function (Transfer $record, array $data): void {
                        app(CommentOnTransfer::class)->handle(self::actor(), $record, (string) $data['body']);
                        Notification::make()->title(__('transfers.review.commented'))->success()->send();
                    }),
                Action::make('assignFinal')
                    ->label(__('transfers.review.assign_final'))
                    ->visible(function (Transfer $record): bool {
                        $assignee = $record->assignee;

                        return $record->comments()->exists()
                            && $assignee instanceof User
                            && self::actor()->can('assign', [$record, $assignee]);
                    })
                    ->action(function (Transfer $record): void {
                        $assignee = $record->assignee;
                        if (! $assignee instanceof User) {
                            return;
                        }
                        app(AssignTransferReview::class)->handle(self::actor(), $record, $assignee);
                        Notification::make()->title(__('transfers.review.assigned_final'))->success()->send();
                    }),
                Action::make('finalReview')
                    ->label(__('transfers.review.final'))
                    ->visible(fn (Transfer $record): bool => self::actor()->can('finalReview', $record))
                    ->schema([
                        Textarea::make('final_note')
                            ->label(__('transfers.review.final_note'))
                            ->required()
                            ->maxLength(2000)
                            ->rule(new NoBankAccountInText),
                    ])
                    ->action(function (Transfer $record, array $data): void {
                        app(RecordFinalReview::class)->handle(self::actor(), $record, (string) $data['final_note']);
                        Notification::make()->title(__('transfers.review.final_done'))->success()->send();
                    }),
            ]);
    }

    private static function actor(): User
    {
        /** @var User */
        return Auth::user();
    }

    /**
     * @return Collection<int, User>
     */
    private static function reviewers(): Collection
    {
        return User::query()
            ->where('role', UserRole::Supervisor)
            ->where('is_active', true)
            ->permission(PermissionKey::TransfersReview->value)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }
}
