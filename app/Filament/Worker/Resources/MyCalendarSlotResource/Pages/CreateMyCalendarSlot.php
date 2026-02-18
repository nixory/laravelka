<?php

namespace App\Filament\Worker\Resources\MyCalendarSlotResource\Pages;

use App\Filament\Worker\Resources\MyCalendarSlotResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMyCalendarSlot extends CreateRecord
{
    protected static string $resource = MyCalendarSlotResource::class;

    protected function getHeaderActions(): array
    {
        $tz = 'Europe/Moscow';

        return [
            Action::make('preset_today_evening')
                ->label('Сегодня 18:00–00:00')
                ->icon('heroicon-o-moon')
                ->color('gray')
                ->action(function (): void {
                    $tz = 'Europe/Moscow';
                    $start = Carbon::now($tz)->setTime(18, 0)->format('Y-m-d H:i:s');
                    $end = Carbon::now($tz)->addDay()->setTime(0, 0)->format('Y-m-d H:i:s');
                    $this->form->fill([
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'duration_preset' => '360',
                        'status' => 'available',
                    ]);
                }),

            Action::make('preset_tomorrow_evening')
                ->label('Завтра 18:00–00:00')
                ->icon('heroicon-o-moon')
                ->color('gray')
                ->action(function (): void {
                    $tz = 'Europe/Moscow';
                    $start = Carbon::now($tz)->addDay()->setTime(18, 0)->format('Y-m-d H:i:s');
                    $end = Carbon::now($tz)->addDays(2)->setTime(0, 0)->format('Y-m-d H:i:s');
                    $this->form->fill([
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'duration_preset' => '360',
                        'status' => 'available',
                    ]);
                }),

            Action::make('preset_today_full')
                ->label('Сегодня 10:00–22:00')
                ->icon('heroicon-o-sun')
                ->color('gray')
                ->action(function (): void {
                    $tz = 'Europe/Moscow';
                    $start = Carbon::now($tz)->setTime(10, 0)->format('Y-m-d H:i:s');
                    $end = Carbon::now($tz)->setTime(22, 0)->format('Y-m-d H:i:s');
                    $this->form->fill([
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'duration_preset' => '480',
                        'status' => 'available',
                    ]);
                }),

            Action::make('preset_tomorrow_full')
                ->label('Завтра 10:00–22:00')
                ->icon('heroicon-o-sun')
                ->color('gray')
                ->action(function (): void {
                    $tz = 'Europe/Moscow';
                    $start = Carbon::now($tz)->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');
                    $end = Carbon::now($tz)->addDay()->setTime(22, 0)->format('Y-m-d H:i:s');
                    $this->form->fill([
                        'starts_at' => $start,
                        'ends_at' => $end,
                        'duration_preset' => '480',
                        'status' => 'available',
                    ]);
                }),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $workerId = Filament::auth()->user()?->workerProfile?->id;
        $data['worker_id'] = $workerId;
        $data['source'] = 'manual';

        return $data;
    }
}


