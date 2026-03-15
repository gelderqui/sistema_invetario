<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Role extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Role $role): void {
            if ($role->users()->exists()) {
                throw ValidationException::withMessages([
                    'role' => ['No se puede eliminar el rol porque tiene usuarios asignados.'],
                ]);
            }
        });
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_system',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'bool',
            'activo' => 'bool',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}