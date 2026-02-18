<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Worker;
use App\Services\OrderAssignmentService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Заказы';
    protected static ?string $modelLabel = 'Заказ';
    protected static ?string $pluralModelLabel = 'Заказы';

    protected static ?string $navigationGroup = 'Операции';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('external_source')
                    ->maxLength(255),
                Forms\Components\TextInput::make('external_order_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('client_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('client_phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\TextInput::make('client_email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('service_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('service_price')
                    ->numeric()
                    ->step(0.01)
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at'),
                Forms\Components\DateTimePicker::make('ends_at'),
                Forms\Components\Select::make('status')
                    ->options([
                        'new' => 'Новый',
                        'assigned' => 'Назначен',
                        'accepted' => 'Принят',
                        'in_progress' => 'В работе',
                        'done' => 'Выполнен',
                        'cancelled' => 'Отменён',
                    ])
                    ->required(),
                Forms\Components\Select::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('assigned_by_user_id')
                    ->relationship('assignedBy', 'email')
                    ->searchable()
                    ->preload(),
                Forms\Components\DateTimePicker::make('accepted_at'),
                Forms\Components\DateTimePicker::make('completed_at'),
                Forms\Components\DateTimePicker::make('cancelled_at'),
                Forms\Components\Placeholder::make('woo_summary')
                    ->label('Детали Woo')
                    ->content(function (?Order $record): string {
                        if (!$record) {
                            return 'Сохраните заказ, чтобы увидеть детали.';
                        }

                        $parts = [];
                        $parts[] = 'План: ' . ($record->wooPlan() ?: '-');
                        $parts[] = 'Часы: ' . ($record->wooHours() ?: '-');
                        $parts[] = 'Доп услуги: ' . ($record->wooAddons() ?: '-');
                        $parts[] = 'Telegram: ' . ($record->wooClientTelegram() ?: '-');
                        $parts[] = 'Discord: ' . ($record->wooClientDiscord() ?: '-');

                        return implode("\n", $parts);
                    })
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('meta_json_readonly')
                    ->label('Мета (только чтение, JSON)')
                    ->formatStateUsing(fn(?Order $record): string => json_encode(
                        $record?->meta ?? [],
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ) ?: '{}')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(18)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Верхняя строка: статус / воркер / сумма / сессия ──────────────
            Infolists\Components\Section::make()
                ->schema([
                    Infolists\Components\TextEntry::make('status_label')
                        ->label('Статус')
                        ->state(fn(Order $record): string => $record->statusLabel())
                        ->badge()
                        ->color(fn(Order $record): string => match ((string) $record->status) {
                            'new' => 'gray',
                            'assigned' => 'info',
                            'accepted' => 'warning',
                            'in_progress' => 'primary',
                            'done' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray',
                        })
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                    Infolists\Components\TextEntry::make('worker.display_name')
                        ->label('Работница')
                        ->placeholder('Не назначена')
                        ->icon('heroicon-o-user')
                        ->iconColor('primary'),

                    Infolists\Components\TextEntry::make('service_price')
                        ->label('Сумма')
                        ->money('RUB')
                        ->icon('heroicon-o-banknotes')
                        ->iconColor('success'),

                    Infolists\Components\TextEntry::make('sessionRange')
                        ->label('Сессия')
                        ->icon('heroicon-o-calendar-days')
                        ->state(function (Order $record): string {
                            $date = $record->wooSessionDate();
                            $time = $record->wooSessionTime();
                            if ($date || $time) {
                                return trim(($date ?: '') . ' ' . ($time ?: ''));
                            }

                            if ($record->starts_at && $record->ends_at) {
                                return $record->starts_at->timezone('Europe/Moscow')->format('d.m.Y H:i') . ' - ' . $record->ends_at->timezone('Europe/Moscow')->format('H:i');
                            }

                            return '-';
                        }),

                    Infolists\Components\TextEntry::make('external_order_id')
                        ->label('Woo ID')
                        ->placeholder('-')
                        ->icon('heroicon-o-hashtag'),

                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Создан')
                        ->state(fn(Order $record): string => $record->created_at?->timezone('Europe/Moscow')->format('d.m.Y H:i') ?? '-')
                        ->icon('heroicon-o-clock'),
                ])
                ->columns(6)
                ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800/50']),

            // ── Клиент ────────────────────────────────────────────────────────
            Infolists\Components\Section::make('Клиент')
                ->icon('heroicon-o-user-circle')
                ->collapsible(false)
                ->schema([
                    Infolists\Components\TextEntry::make('client_name')->label('Имя'),
                    Infolists\Components\TextEntry::make('client_email')->label('Email')->placeholder('-'),
                    Infolists\Components\TextEntry::make('client_phone')->label('Телефон')->placeholder('-'),
                    Infolists\Components\TextEntry::make('wooClientTelegram')
                        ->label('Telegram')
                        ->state(fn(Order $record): string => $record->wooClientTelegram() ?: '-'),
                    Infolists\Components\TextEntry::make('wooClientDiscord')
                        ->label('Discord')
                        ->state(fn(Order $record): string => $record->wooClientDiscord() ?: '-'),
                    Infolists\Components\TextEntry::make('wooDesiredDateTime')
                        ->label('Желаемая дата/время')
                        ->state(fn(Order $record): string => $record->wooDesiredDateTime() ?: '-'),
                ])
                ->columns(3),

            // ── Услуга / Тариф / Допы ─────────────────────────────────────────
            Infolists\Components\Section::make('Услуга и тариф')
                ->icon('heroicon-o-shopping-bag')
                ->collapsible(false)
                ->schema([
                    Infolists\Components\TextEntry::make('service_name')->label('Товар'),
                    Infolists\Components\TextEntry::make('wooPlan')
                        ->label('Тариф')
                        ->state(fn(Order $record): string => $record->wooPlan() ?: '-')
                        ->badge()
                        ->color('info'),
                    Infolists\Components\TextEntry::make('wooHours')
                        ->label('Часы')
                        ->state(fn(Order $record): string => $record->wooHours() ?: '-'),
                    Infolists\Components\TextEntry::make('wooAddons')
                        ->label('Доп услуги')
                        ->state(fn(Order $record): string => $record->wooAddons() ?: '-')
                        ->placeholder('-'),
                    Infolists\Components\TextEntry::make('sessionRange2')
                        ->label('Сессия')
                        ->state(function (Order $record): string {
                            $date = $record->wooSessionDate();
                            $time = $record->wooSessionTime();
                            if ($date || $time) {
                                return trim(($date ?: '') . ' ' . ($time ?: ''));
                            }

                            if ($record->starts_at && $record->ends_at) {
                                return $record->starts_at->timezone('Europe/Moscow')->format('d.m.Y H:i') . ' - ' . $record->ends_at->timezone('Europe/Moscow')->format('H:i');
                            }

                            return '-';
                        }),
                    Infolists\Components\TextEntry::make('service_price')
                        ->label('Итого')
                        ->money('RUB'),
                ])
                ->columns(3),

            // ── Таймлайн событий ──────────────────────────────────────────────
            Infolists\Components\Section::make('Таймлайн событий')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('timeline')
                        ->state(fn(Order $record): array => $record->timelineEvents())
                        ->schema([
                            Infolists\Components\TextEntry::make('type')
                                ->label('Тип')
                                ->badge()
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'created' => 'Создан',
                                    'assigned' => 'Назначен',
                                    'accepted' => 'Принят',
                                    'done' => 'Выполнен',
                                    'cancelled' => 'Отменён',
                                    'declined' => 'Отказ',
                                    default => $state,
                                })
                                ->color(fn(string $state): string => match ($state) {
                                    'created' => 'gray',
                                    'assigned' => 'info',
                                    'accepted' => 'warning',
                                    'done' => 'success',
                                    'cancelled' => 'danger',
                                    'declined' => 'danger',
                                    default => 'gray',
                                }),
                            Infolists\Components\TextEntry::make('title')
                                ->label('Событие'),
                            Infolists\Components\TextEntry::make('at')
                                ->label('Время (МСК)'),
                            Infolists\Components\TextEntry::make('note')
                                ->label('Комментарий')
                                ->placeholder('-')
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->contained(false),
                ])
                ->collapsible()
                ->collapsed(false),

            // ── Позиции заказа (свёрнуто) ─────────────────────────────────────
            Infolists\Components\Section::make('Позиции заказа')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('meta.line_items')
                        ->schema([
                            Infolists\Components\TextEntry::make('name')->label('Товар'),
                            Infolists\Components\TextEntry::make('quantity')->label('Кол-во'),
                            Infolists\Components\TextEntry::make('total')->label('Сумма'),
                            Infolists\Components\KeyValueEntry::make('meta')
                                ->label('Мета')
                                ->columnSpanFull(),
                        ])
                        ->columns(3),
                ])
                ->collapsible()
                ->collapsed(),

            // ── Мета (свёрнуто) ───────────────────────────────────────────────
            Infolists\Components\Section::make('Мета заказа')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('meta.order_meta')
                        ->label('Мета'),
                ])
                ->collapsible()
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('external_order_id')
                    ->label('Woo ID')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_name')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('service_price')
                    ->money('RUB')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'new' => 'Новый',
                        'assigned' => 'Назначен',
                        'accepted' => 'Принят',
                        'in_progress' => 'В работе',
                        'done' => 'Выполнен',
                        'cancelled' => 'Отменён',
                        default => $state,
                    })
                    ->colors([
                        'gray' => 'new',
                        'info' => 'assigned',
                        'warning' => 'accepted',
                        'primary' => 'in_progress',
                        'success' => 'done',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('worker.display_name')
                    ->label('Работница')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('waiting_minutes')
                    ->label('Ожидание')
                    ->state(function (Order $record): string {
                        if ($record->status !== Order::STATUS_NEW || $record->worker_id) {
                            return '—';
                        }
                        $mins = (int) now()->diffInMinutes($record->created_at);
                        return "{$mins} мин";
                    })
                    ->color(function (Order $record): string {
                        if ($record->status !== Order::STATUS_NEW || $record->worker_id) {
                            return 'gray';
                        }
                        $mins = (int) now()->diffInMinutes($record->created_at);
                        return $mins > 10 ? 'danger' : 'warning';
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d.m.Y H:i', 'Europe/Moscow')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'Новый',
                        'assigned' => 'Назначен',
                        'accepted' => 'Принят',
                        'in_progress' => 'В работе',
                        'done' => 'Выполнен',
                        'cancelled' => 'Отменён',
                    ]),
                SelectFilter::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->label('Работница'),
            ])
            ->actions([
                Action::make('assignWorker')
                    ->label('Назначить')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->visible(fn(Order $record): bool => !$record->worker_id && $record->status === Order::STATUS_NEW)
                    ->form([
                        Forms\Components\Select::make('worker_id')
                            ->label('Работница')
                            ->options(
                                Worker::query()
                                    ->where('is_active', true)
                                    ->orderBy('display_name')
                                    ->pluck('display_name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Order $record, array $data): void {
                        $record->update([
                            'worker_id' => $data['worker_id'],
                            'status' => Order::STATUS_ASSIGNED,
                            'assigned_by_user_id' => Filament::auth()->id(),
                        ]);

                        Notification::make()
                            ->title('Работница назначена')
                            ->success()
                            ->send();
                    }),

                Action::make('autoAssign')
                    ->label('Автоназначение')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->visible(fn(Order $record): bool => $record->isAutoAssignable())
                    ->action(function (Order $record, OrderAssignmentService $assignmentService): void {
                        $worker = $assignmentService->assign($record);

                        if ($worker) {
                            Notification::make()
                                ->title("Назначено: {$worker->display_name}")
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Нет доступной работницы для автоназначения')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
