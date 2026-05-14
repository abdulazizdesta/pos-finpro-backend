<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'transaction_code',
        'user_id',
        'outlet_id',
        'shift_id',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'total',
        'payment_method',
        'payment_status',
        'notes',
    ];

    protected $casts = [
        'subtotal'        => 'integer',
        'discount_amount' => 'integer',
        'tax_amount'      => 'integer',
        'total'           => 'integer',
        'created_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(TransactionDiscount::class);
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(TransactionTax::class);
    }
}
