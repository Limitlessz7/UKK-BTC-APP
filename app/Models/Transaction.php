<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TransactionItem;
use Illuminate\Support\Facades\Schema;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    // default to id; we'll detect actual column at runtime
    protected $table = 'transactions';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    // cached resolved primary key name
    protected ?string $resolvedPrimaryKey = null;

    protected $fillable = [
        'total',
        'transaction_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    // Ensure relationship to transaction_items is present for Filament ->relationship('items')
    public function items(): HasMany
    {
        // detect the foreign key column used on the transaction_items table
        $fk = $this->resolveTransactionItemsForeignKey();

        // use the model primary key name as local key
        return $this->hasMany(TransactionItem::class, $fk, $this->getKeyName());
    }

    /**
     * Resolve which foreign key column exists on transaction_items table that references transactions.
     */
    protected function resolveTransactionItemsForeignKey(): string
    {
        $table = 'transaction_items';

        // check common variants in order of preference
        $candidates = [
            'trxi_transaction_id',
            'trx_transaction_id',
            'transaction_id',
            'transactions_id',
        ];

        foreach ($candidates as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        // fallback to 'trxi_transaction_id' (will error if not present)
        return 'trxi_transaction_id';
    }

    // Determine primary key name at runtime by checking the DB schema
    public function getKeyName()
    {
        if ($this->resolvedPrimaryKey !== null) {
            return $this->resolvedPrimaryKey;
        }

        $table = $this->getTable();

        // prefer trx_id if it exists, otherwise fall back to id
        if (Schema::hasColumn($table, 'trx_id')) {
            $key = 'trx_id';
        } elseif (Schema::hasColumn($table, 'id')) {
            $key = 'id';
        } else {
            // fallback to the model's configured property
            $key = parent::getKeyName();
        }

        $this->resolvedPrimaryKey = $key;

        return $this->resolvedPrimaryKey;
    }

    // Virtual total attribute computed from related items (tries trxi_subtotal then subtotal)
    public function getTotalAttribute(): float
    {
        $itemsTable = 'transaction_items';

        $subtotalColumn = Schema::hasColumn($itemsTable, 'trxi_subtotal')
            ? 'trxi_subtotal'
            : (Schema::hasColumn($itemsTable, 'subtotal') ? 'subtotal' : 'trxi_subtotal');

        return (float) $this->items()->sum($subtotalColumn);
    }
}
