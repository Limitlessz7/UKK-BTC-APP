<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'total',
        'transaction_date',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function items()
    {
     return $this->hasMany(\App\Models\TransactionItem::class);
    }
}
