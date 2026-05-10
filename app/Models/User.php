<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role'=> UserRole::class,
        ];
    }

    protected $fillable = [
        'business_id',
        'outlet_id',
        'name',
        'email',
        'password',
        'pin',
        'role',
        'is_active',
        'failed_attempts',
    ];

    protected $hidden = [
        'password', 'remember_token', 'pin'
    ];

    public function business():BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outlet():BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
