<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PhpParser\Node\Expr\Cast;

class Stock extends Model
{
    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'product_id', 
        'variant_id',
        'outlet_id',
        'quantity',
        'min_threshold',
        'reserved_quantity',
    ];

    protected $casts = [
        'quantity'  => 'integer',
        'min_threshold'=> 'integer',
        'reserved_quantity'        => 'integer',
    ];

    public function product():BelongsTo 
    {
        return $this->belongsTo(Product::class);
    }

    public function outlet():BelongsTo 
    {
        return $this->belongsTo(Outlet::class);
    }

    public function mutations():HasMany 
    {
        return $this->hasMany(StockMutation::class);
    }

    public function getAvailableQuantityAttribute():int
    {
        return $this->quantity - $this->reserved_quantity;

    }
}
