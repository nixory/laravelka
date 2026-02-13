<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MessageResource\Pages;
use App\Models\Message;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center-text';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('chat_id')
                    ->relationship('chat', 'id')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('sender_type')
                    ->options([
                        'client' => 'Client',
                        'worker' => 'Worker',
                        'operator' => 'Operator',
                        'system' => 'System',
                    ])
                    ->required(),
                Forms\Components\Select::make('sender_user_id')
                    ->relationship('senderUser', 'email')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('worker_id')
                    ->relationship('worker', 'display_name')
                    ->searchable()
                    ->preload(),
                Forms\Components\Textarea::make('body')
                    ->columnSpanFull(),
                Forms\Components\KeyValue::make('payload')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_read')
                    ->required(),
                Forms\Components\DateTimePicker::make('read_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('chat_id')
                    ->label('Chat')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sender_type')
                    ->badge(),
                Tables\Columns\TextColumn::make('senderUser.email')
                    ->label('Sender user')
                    ->searchable(),
                Tables\Columns\TextColumn::make('worker.display_name')
                    ->label('Worker')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_read')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sender_type')
                    ->options([
                        'client' => 'Client',
                        'worker' => 'Worker',
                        'operator' => 'Operator',
                        'system' => 'System',
                    ]),
                SelectFilter::make('is_read')
                    ->options([
                        '1' => 'Read',
                        '0' => 'Unread',
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListMessages::route('/'),
            'create' => Pages\CreateMessage::route('/create'),
            'edit' => Pages\EditMessage::route('/{record}/edit'),
        ];
    }
}
