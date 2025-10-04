<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return TransactionResource::mutateFormDataBeforeSave($data);
    }

    protected function afterSave(): void
    {
        $total = 0;

        foreach ($this->record->items as $item) {
            $total += $item->subtotal;
        }

        $this->record->update(['total' => $total]);
    }
}
    