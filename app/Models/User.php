<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_CUSTOMER = 'customer';
    public const ROLES = [self::ROLE_ADMIN, self::ROLE_CUSTOMER];

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed', // plain passwords are hashed automatically
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
