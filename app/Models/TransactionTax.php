<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionTax extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'tax_settings_id',
        'tax_name',
        'tax_rate',
        'tax_amount',
    ];

    protected $casts = [
        'tax_rate'   => 'float',
        'tax_amount' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function taxSetting(): BelongsTo
    {
        return $this->belongsTo(TaxSetting::class, 'tax_settings_id');
    }
}
