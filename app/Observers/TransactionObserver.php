<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionObserver
{
    /**
     * Handle the Transaction "created" event.
     */
    public function created(Transaction $transaction): void
    {
        // Load items with related products
        $items = $transaction->items()->with('product')->get();

        if ($items->isEmpty()) {
            return;
        }

        // Perform stock updates in a DB transaction
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                $product = $item->product;

                if (! $product) {
                    continue;
                }

                $qty = (int) ($item->quantity ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                // Decrement stock but never below zero
                $product->stock = max(0, (int) $product->stock - $qty);
                $product->save();
            }
        });
    }
}
