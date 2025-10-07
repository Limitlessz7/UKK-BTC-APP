<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LaporanHarian extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.pages.laporan-harian';
    protected static ?string $title = 'Daily Report';

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
            // use the migration primary key name
            Tables\Columns\TextColumn::make('trx_id')->label('ID')->sortable(),

            Tables\Columns\TextColumn::make('transaction_date')->dateTime()->label('Tanggal'),

            // Added: show items with product name, qty and subtotal
            Tables\Columns\TextColumn::make('items')
                ->label('Detail Produk')
                ->formatStateUsing(function ($state, $record) {
                    if (! $record) {
                        return '';
                    }

                    $toFloat = function ($value): float {
                        $clean = filter_var((string) $value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION | FILTER_FLAG_ALLOW_THOUSAND);
                        return $clean === '' ? 0.0 : (float) str_replace(',', '.', str_replace('.', '', $clean));
                    };

                    $items = collect($record->items)->map(function ($item) use ($toFloat) {
                        // product name: prefer loaded relation pdc_name, then name, then lookup by trxi_product_id / product_id
                        $productName = $item->product?->pdc_name
                            ?? $item->product?->name
                            ?? \App\Models\Product::where('pdc_id', data_get($item, 'trxi_product_id', data_get($item, 'product_id')))->value('pdc_name')
                            ?? '—';

                        // quantity: prefer 'trxi_quantity' then 'quantity'
                        $qty = (int) data_get($item, 'trxi_quantity', data_get($item, 'quantity', 0));

                        // subtotal: prefer 'trxi_subtotal' then 'subtotal'
                        $subtotalValue = data_get($item, 'trxi_subtotal', data_get($item, 'subtotal', null));

                        if ($subtotalValue === null) {
                            $price = data_get($item, 'trxi_price', data_get($item, 'price', 0));
                            $subtotalValue = $qty * ($price ?? 0);
                        }

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
        $date = Carbon::parse($this->selectedDate)->toDateString();

        // get transaction IDs for the selected date
        $transactionIds = Transaction::whereDate('transaction_date', $date)->pluck((new Transaction)->getKeyName());

        if ($transactionIds->isEmpty()) {
            return 0.0;
        }

        $itemsTable = 'transaction_items';

        // detect foreign key column on transaction_items
        $fkCandidates = [
            'trxi_transaction_id',
            'trx_transaction_id',
            'transaction_id',
            'transactions_id',
        ];
        $fk = null;
        foreach ($fkCandidates as $c) {
            if (Schema::hasColumn($itemsTable, $c)) {
                $fk = $c;
                break;
            }
        }
        $fk = $fk ?? 'trxi_transaction_id';

        // detect subtotal column; fallback to computing price * quantity
        $subtotalCandidates = ['trxi_subtotal', 'subtotal', 'trxi_total', 'total'];
        $subtotalColumn = null;
        foreach ($subtotalCandidates as $c) {
            if (Schema::hasColumn($itemsTable, $c)) {
                $subtotalColumn = $c;
                break;
            }
        }

        if ($subtotalColumn) {
            $sum = DB::table($itemsTable)
                ->whereIn($fk, $transactionIds)
                ->sum($subtotalColumn);
            return (float) $sum;
        }

        // fallback: compute SUM(trxi_price * trxi_quantity) in DB
        $priceCol = Schema::hasColumn($itemsTable, 'trxi_price') ? 'trxi_price' : (Schema::hasColumn($itemsTable, 'price') ? 'price' : null);
        $qtyCol = Schema::hasColumn($itemsTable, 'trxi_quantity') ? 'trxi_quantity' : (Schema::hasColumn($itemsTable, 'quantity') ? 'quantity' : null);

        if ($priceCol && $qtyCol) {
            $sum = DB::table($itemsTable)
                ->whereIn($fk, $transactionIds)
                ->selectRaw("SUM(COALESCE(`{$priceCol}`,0) * COALESCE(`{$qtyCol}`,0)) as aggregate")
                ->value('aggregate');
            return (float) ($sum ?? 0.0);
        }

        // ultimate fallback: load items and compute in PHP (safe but less efficient)
        $sum = TransactionItem::whereIn($fk, $transactionIds)->get()->reduce(function ($carry, $item) {
            $price = data_get($item, 'trxi_price', data_get($item, 'price', 0));
            $qty = data_get($item, 'trxi_quantity', data_get($item, 'quantity', 0));
            $subtotal = data_get($item, 'trxi_subtotal', data_get($item, 'subtotal', null));
            if ($subtotal === null) {
                $subtotal = ($price ?? 0) * ($qty ?? 0);
            }
            return $carry + (float) $subtotal;
        }, 0.0);

        return (float) $sum;
    }
}


