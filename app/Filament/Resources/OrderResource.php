<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\OrderAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationGroup = 'Operations';

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
                        'new' => 'New',
                        'assigned' => 'Assigned',
                        'accepted' => 'Accepted',
                        'in_progress' => 'In progress',
                        'done' => 'Done',
                        'cancelled' => 'Cancelled',
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
                Forms\Components\KeyValue::make('meta')
                    ->columnSpanFull(),
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
                    ->label('External ID')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('client_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_price')
                    ->money('USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'new',
                        'info' => 'assigned',
                        'warning' => 'accepted',
                        'primary' => 'in_progress',
                        'success' => 'done',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('worker.display_name')
                    ->label('Worker')
                    ->searchable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'new' => 'New',
                        'assigned' => 'Assigned',
                        'accepted' => 'Accepted',
                        'in_progress' => 'In progress',
                        'done' => 'Done',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->label('Worker'),
            ])
            ->actions([
                Action::make('autoAssign')
                    ->label('Auto assign')
                    ->icon('heroicon-o-sparkles')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record): bool => $record->isAutoAssignable())
                    ->action(function (Order $record, OrderAssignmentService $assignmentService): void {
                        $worker = $assignmentService->assign($record);

                        if ($worker) {
                            Notification::make()
                                ->title("Assigned to {$worker->display_name}")
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('No available worker for auto-assign')
                            ->warning()
                            ->send();
                    }),
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
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
