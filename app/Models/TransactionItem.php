<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'price',
        'subtotal',
    ];

    protected static function booted()
    {
        // Kurangi stok saat item dibuat
        static::created(function ($item) {
            $product = $item->product;
            if ($product) {
                $product->decrement('stock', $item->quantity);
            }
        });

        // Tambah stok kembali saat item dihapus
        static::deleted(function ($item) {
            $product = $item->product;
            if ($product) {
                $product->increment('stock', $item->quantity);
            }
        });

        // Update stok saat item diubah
        static::updating(function ($item) {
            $product = $item->product;
            if ($product) {
                $originalQty = $item->getOriginal('quantity');
                $product->increment('stock', $originalQty);
                $product->decrement('stock', $item->quantity);
            }
        });
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
    