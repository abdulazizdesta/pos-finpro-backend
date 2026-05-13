<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id', 'category_id', 'name', 'sku',
        'description', 'price', 'cost_price', 'image_url',
        'has_variants', 'is_active', 'deleted_by',
    ];

    protected $casts = [
        'price'        => 'float',
        'cost_price'   => 'float',
        'has_variants' => 'boolean',
        'is_active'    => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}