<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WorkerProfileEditRequestResource\Pages;
use App\Models\Worker;
use App\Models\WorkerProfileEditRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkerProfileEditRequestResource extends Resource
{
    protected static ?string $model = WorkerProfileEditRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Воркеры';
    protected static ?string $navigationLabel = 'Модерация анкет';
    protected static ?string $modelLabel = 'Заявка на модерацию';
    protected static ?string $pluralModelLabel = 'Заявки на модерацию';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->label('Воркер')
                    ->required()
                    ->disabled(),
                Forms\Components\Select::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'В ожидании',
                        'approved' => 'Одобрено',
                        'rejected' => 'Отклонено',
                    ])
                    ->required(),
                Forms\Components\KeyValue::make('data')
                    ->label('Измененные данные')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('worker.display_name')
                    ->label('Воркер')
                    ->searchable()
                    ->sortable()
                    ->url(fn(WorkerProfileEditRequest $record): string => WorkerResource::getUrl('edit', ['record' => $record->worker_id])),
                Tables\Columns\TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Дата создания')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'В ожидании',
                        'approved' => 'Одобрено',
                        'rejected' => 'Отклонено',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Одобрить')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->visible(fn(WorkerProfileEditRequest $record) => $record->status === 'pending')
                    ->action(function (WorkerProfileEditRequest $record) {
                        self::approveRequest($record);
                        Notification::make()->title('Заявка одобрена')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->label('Отклонить')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Причина отказа')
                            ->required(),
                    ])
                    ->visible(fn(WorkerProfileEditRequest $record) => $record->status === 'pending')
                    ->action(function (WorkerProfileEditRequest $record, array $data) {
                        $record->update(['status' => 'rejected']);
                        self::notifyWorker($record->worker, 'rejected', $data['reason']);
                        Notification::make()->title('Заявка отклонена')->success()->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function approveRequest(WorkerProfileEditRequest $request): void
    {
        $worker = $request->worker;
        $data = $request->data;

        $servicesCustom = $worker->services_custom ?? [];
        $servicesCustom['session_options'] = $data['session_options'] ?? [];
        $servicesCustom['flirt_level'] = $data['flirt_level'] ?? null;
        $servicesCustom['character_styles'] = $data['character_styles'] ?? [];
        $servicesCustom['extra_services'] = [
            'voice' => $data['extra_services_voice'] ?? [],
            'roleplay' => $data['extra_services_roleplay'] ?? [],
            'gaming' => $data['extra_services_gaming'] ?? [],
            'entertainment' => $data['extra_services_entertainment'] ?? [],
            'creative' => $data['extra_services_creative'] ?? [],
            'activities' => $data['extra_services_activities'] ?? [],
        ];
        $servicesCustom['fan_club'] = [
            'enabled' => $data['fan_club_enabled'] ?? false,
            'type' => $data['fan_club_type'] ?? null,
            'format' => $data['fan_club_format'] ?? [],
            'frequency' => $data['fan_club_frequency'] ?? null,
            'price' => $data['fan_club_price'] ?? null,
        ];
        $servicesCustom['boundaries'] = [
            'list' => $data['boundaries'] ?? [],
            'custom' => $data['boundaries_custom'] ?? null,
        ];
        $servicesCustom['client_interaction_mode'] = $data['client_interaction_mode'] ?? null;
        $servicesCustom['promotion_mode'] = $data['promotion_mode'] ?? null;
        $servicesCustom['content_permission'] = $data['content_permission'] ?? null;
        $servicesCustom['custom_services'] = $data['custom_services'] ?? null;

        $schedulePrefs = $worker->schedule_preferences ?? [];
        $schedulePrefs['slots'] = $data['schedule_slots'] ?? [];

        $worker->update([
            'display_name' => $data['display_name'] ?? $worker->display_name,
            'city' => $data['city'] ?? $worker->city,
            'timezone' => $data['timezone'] ?? $worker->timezone,
            'age' => $data['age'] ?? $worker->age,
            'description' => $data['description'] ?? $worker->description,
            'experience' => $data['experience'] ?? $worker->experience,
            'preferred_format' => $data['preferred_format'] ?? $worker->preferred_format,
            'favorite_games' => $data['favorite_games'] ?? $worker->favorite_games,
            'favorite_anime' => $data['favorite_anime'] ?? $worker->favorite_anime,
            'photo_main' => $data['photo_main'] ?? $worker->photo_main,
            'photos_gallery' => $data['photos_gallery'] ?? $worker->photos_gallery,
            'audio_path' => $data['audio_path'] ?? $worker->audio_path,
            'services_custom' => $servicesCustom,
            'schedule_preferences' => $schedulePrefs,
        ]);

        $request->update(['status' => 'approved']);

        self::notifyWorker($worker, 'approved');
    }

    private static function notifyWorker($worker, $status, $reason = null): void
    {
        $chatId = $worker->telegram_chat_id;
        if (!$chatId)
            return;

        $botToken = config('services.telegram.bot_token');
        if (!$botToken)
            return;

        $text = $status === 'approved'
            ? "✅ *Твои изменения профиля одобрены!*\n\nОни уже появились на сайте."
            : "❌ *Твои изменения профиля были отклонены.*\n\n*Причина:* {$reason}\n\nПожалуйста, исправь ошибки и отправь заявку снова.";

        try {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send profile edit status TG notification: ' . $e->getMessage());
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageWorkerProfileEditRequests::route('/'),
        ];
    }
}
