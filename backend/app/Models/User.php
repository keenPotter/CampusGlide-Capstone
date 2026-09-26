<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Keep these in sync with the `role` enum on the users table
    public const ROLE_ADMINISTRATOR = 'administrator';
    public const ROLE_DRIVER = 'driver';
    public const ROLE_FACULTY = 'faculty';
    public const ROLE_GUARD = 'guard';

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'last_name',
        'position',
        'phone_number',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function vehicleRequests()
    {
        return $this->hasMany(VehicleRequest::class, 'requester_id');
    }
}
