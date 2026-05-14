<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxSetting extends Model
{
    use SoftDeletes;

    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = [
        'business_id',
        'name',
        'rate',
        'is_active',
    ];

    protected $casts = [
        'rate'      => 'float',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
