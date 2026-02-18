<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Worker;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return 'Заказ #' . $this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignWorker')
                ->label('Назначить воркера')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->visible(fn (): bool => ! $this->record->worker_id)
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
                ->action(function (array $data): void {
                    $this->record->update([
                        'worker_id'          => $data['worker_id'],
                        'status'             => Order::STATUS_ASSIGNED,
                        'assigned_by_user_id' => Filament::auth()->id(),
                    ]);

                    $this->refreshFormData(['worker_id', 'status', 'assigned_by_user_id']);

                    Notification::make()
                        ->title('Работница назначена')
                        ->success()
                        ->send();
                }),

            Action::make('reassignWorker')
                ->label('Переназначить')
                ->icon('heroicon-o-arrows-right-left')
                ->color('warning')
                ->visible(fn (): bool => (bool) $this->record->worker_id)
                ->form([
                    Forms\Components\Select::make('worker_id')
                        ->label('Новая работница')
                        ->options(
                            Worker::query()
                                ->where('is_active', true)
                                ->orderBy('display_name')
                                ->pluck('display_name', 'id')
                        )
                        ->searchable()
                        ->required()
                        ->default($this->record->worker_id),
                    Forms\Components\Textarea::make('note')
                        ->label('Причина переназначения')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'worker_id'          => $data['worker_id'],
                        'status'             => Order::STATUS_ASSIGNED,
                        'assigned_by_user_id' => Filament::auth()->id(),
                    ]);

                    $this->refreshFormData(['worker_id', 'status', 'assigned_by_user_id']);

                    Notification::make()
                        ->title('Работница переназначена')
                        ->success()
                        ->send();
                }),

            Actions\EditAction::make()
                ->label('Редактировать'),
        ];
    }
}
