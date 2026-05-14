<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionDiscount extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'discount_id',
        'discount_code',
        'discount_amount',
    ];

    protected $casts = [
        'discount_amount' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
