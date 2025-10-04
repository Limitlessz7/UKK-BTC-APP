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

    // selected date for the report (YYYY-MM-DD)
    public string $selectedDate;

    public function mount(): void
    {
        // if ?date= exists use it, otherwise default to today
        $this->selectedDate = (string) request()->query('date', Carbon::today()->toDateString());
    }

    // Setup tabel transaksi for the selected date
    protected function getTableQuery()
    {
        $date = Carbon::parse($this->selectedDate)->startOfDay();

        // Eager-load items and their products to avoid N+1 queries
        return Transaction::with('items.product')->whereDate('transaction_date', $date);
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

    // Navigate to previous day
    public function previousDay(): void
    {
        $date = Carbon::parse($this->selectedDate)->subDay()->toDateString();

        // redirect with ?date= to reload page and table
        $this->redirect(request()->fullUrlWithQuery(['date' => $date]));
    }

    // Navigate to next day
    public function nextDay(): void
    {
        $date = Carbon::parse($this->selectedDate)->addDay()->toDateString();

        $this->redirect(request()->fullUrlWithQuery(['date' => $date]));
    }

    // Go to today
    public function goToToday(): void
    {
        $date = Carbon::today()->toDateString();

        $this->redirect(request()->fullUrlWithQuery(['date' => $date]));
    }

    // Set a specific date (YYYY-MM-DD)
    public function setDate(string $date): void
    {
        // validate minimal format by trying to parse
        $parsed = Carbon::parse($date)->toDateString();

        $this->redirect(request()->fullUrlWithQuery(['date' => $parsed]));
    }

    public function getTotalHariIni()
    {
        $date = Carbon::parse($this->selectedDate);
        $sum = Transaction::whereDate('transaction_date', $date)->sum('total');

        // Return numeric value (float) so the Blade view can safely call number_format on it.
        return (float) $sum;
    }
}


