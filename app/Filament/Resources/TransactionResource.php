<?php

namespace App\Filament\Resources;

use Filament\Tables;
use Filament\Forms;
use App\Models\Product;
use App\Models\Transaction;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Resources\TransactionResource\Pages;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = 'Transaksi';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                DateTimePicker::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->default(now())
                    ->required(),

                Repeater::make('items')
                    ->relationship('items')
                    ->label('Detail Produk')
                    ->minItems(1)
                    ->schema([
                        Select::make('product_id')
                            ->label('Produk')
                            ->relationship('product', 'name') // Lebih efisien
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $price = Product::find($state)?->price ?? 0;
                                $set('price', $price);
                                $set('subtotal', $price); // default qty = 1
                            }),

                        TextInput::make('quantity')
                            ->label('Jumlah')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set, $get) {
                                $price = $get('price') ?? 0;
                                $set('subtotal', $state * $price);
                            }),

                        TextInput::make('price')
                            ->label('Harga per Item')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->money('idr', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('items')
                    ->label('Detail Produk')
                    ->formatStateUsing(function ($state, $record) {
                        return collect($record->items)->map(function ($item) {
                            $productName = $item->product?->name ?? '—';
                            $qty = $item->quantity ?? 0;
                            $subtotal = number_format($item->subtotal ?? 0, 0, ',', '.');
                            return "{$productName} x{$qty}: Rp{$subtotal}";
                        })->implode(', ');
                    })
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\Filter::make('Hari Ini')
                    ->query(fn ($query) => $query->whereDate('transaction_date', today())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    /**
     * Hitung total dan isi price + subtotal sebelum create
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $total = 0;

        if (!empty($data['items'])) {
            foreach ($data['items'] as &$item) {
                $product = Product::find($item['product_id']);
                $item['price'] = $product?->price ?? 0;
                $item['subtotal'] = ($item['quantity'] ?? 0) * $item['price'];
                $total += $item['subtotal'];
            }
        }

        $data['total'] = $total;

        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        return static::mutateFormDataBeforeCreate($data);
    }
}
