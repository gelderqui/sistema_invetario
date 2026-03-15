<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'telefono',
        'activo',
        'role_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'activo' => 'bool',
            'password' => 'hashed',
            'deleted_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $roleValues = collect((array) $roles)->filter()->values();

        if ($roleValues->isEmpty() || ! $this->role) {
            return false;
        }

        return $roleValues->contains($this->role->code) || $roleValues->contains($this->role->name);
    }

    public function allPermissions(): Collection
    {
        $role = $this->relationLoaded('role') ? $this->role : $this->role()->with('permissions')->first();

        if (! $role) {
            return collect();
        }

        return $role->loadMissing('permissions')->permissions->sortBy('code')->values();
    }

    public function hasPermission(string|array $permissions): bool
    {
        $permissionValues = collect((array) $permissions)->filter()->values();

        if ($permissionValues->isEmpty()) {
            return false;
        }

        return $this->allPermissions()->contains(function (Permission $permission) use ($permissionValues): bool {
            return $permissionValues->contains($permission->code) || $permissionValues->contains($permission->name);
        });
    }
}
