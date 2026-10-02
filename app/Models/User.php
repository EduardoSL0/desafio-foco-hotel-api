<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'hotel_id',
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'hotel_id' => 'integer',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Usuário pode consultar/operar dados do hotel (admin ou funcionário do hotel). */
    public function worksAt(int $hotelId): bool
    {
        return $this->isAdmin() || $this->hotel_id === $hotelId;
    }

    /** Usuário pode administrar o hotel (admin ou gerente do hotel). */
    public function canManageHotel(int $hotelId): bool
    {
        return $this->isAdmin()
            || ($this->role === UserRole::Manager && $this->hotel_id === $hotelId);
    }
}
