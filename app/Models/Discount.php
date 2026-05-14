<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use SoftDeletes;

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'type',
        'value',
        'min_purchase',
        'max_uses',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'value'        => 'integer',
        'min_purchase' => 'integer',
        'is_active'    => 'boolean',
        'valid_from'   => 'datetime',
        'valid_until'  => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
