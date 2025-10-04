<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return TransactionResource::mutateFormDataBeforeCreate($data);
    }

    protected function afterCreate(): void
    {
        $total = 0;

        foreach ($this->record->items as $item) {
            $total += $item->subtotal;
        }

        $this->record->update(['total' => $total]);
    }
}
