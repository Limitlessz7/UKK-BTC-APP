<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;

class LaporanHarian extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.laporan-harian';
    protected static ?string $title = 'Laporan Harian';

    // Setup tabel transaksi hari ini
    protected function getTableQuery()
    {
        $today = Carbon::today();

        // Eager-load items and their products to avoid N+1 queries
        return Transaction::with('items.product')->whereDate('transaction_date', $today);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('id')->label('ID')->sortable(),
            Tables\Columns\TextColumn::make('transaction_date')->dateTime()->label('Tanggal'),

            // Added: show items with product name, qty and subtotal
            Tables\Columns\TextColumn::make('items')
                ->label('Detail Produk')
                ->formatStateUsing(function ($state, $record) {
                    if (! $record) {
                        return '';
                    }

                    // sanitizer to ensure numeric values for number_format
                    $toFloat = function ($value): float {
                        // remove anything except digits, minus sign and decimal separator
                        $clean = filter_var((string) $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
                        // fallback to 0.0 if empty
                        return $clean === '' ? 0.0 : (float) str_replace(',', '.', str_replace('.', '', $clean));
                    };

                    $items = collect($record->items)->map(function ($item) use ($toFloat) {
                        $productName = $item->product?->name ?? '—';
                        $qty = $item->quantity ?? 0;
                        $subtotalValue = $item->subtotal ?? 0;
                        $subtotal = number_format($toFloat($subtotalValue), 0, ',', '.');
                        return "{$productName} x{$qty}: Rp{$subtotal}";
                    });

                    return $items->implode(', ');
                })
                ->wrap(),

            // total column removed
        ];
    }

    protected function getTableActions(): array
    {
        return [];
    }

    protected function getTableBulkActions(): array
    {
        return [];
    }

    public function getTotalHariIni()
    {
        $today = Carbon::today();
        $sum = Transaction::whereDate('transaction_date', $today)->sum('total');

        // Return numeric value (float) so the Blade view can safely call number_format on it.
        return (float) $sum;
    }
}


