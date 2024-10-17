<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\RoleType;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public function roles()
    {
        return $this->belongsToMany(RoleType::class, 'users_roles', 'user_id', 'role_type_id');
    }

    public function hasRole($roleSlug): bool
    {
        if (is_array($roleSlug)) {
            // Проверка, есть ли хотя бы одна из ролей
            return $this->roles()->whereIn('role_type_slug', $roleSlug)->exists();
        }

        // Проверка одной роли
        return $this->roles()->where('role_type_slug', $roleSlug)->exists();
    }

    public function hasOnlyRoles($roleSlug): bool
    {
        // Если передана одна роль (string), превращаем её в массив
        if (is_string($roleSlug)) {
            $roleSlug = [$roleSlug];
        }

        // Получаем все роли пользователя
        $userRoles = $this->roles()->pluck('role_type_slug')->toArray();
        
        // Сравниваем роли пользователя с переданным массивом
        return empty(array_diff($userRoles, $roleSlug)) && count($userRoles) === count($roleSlug);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
