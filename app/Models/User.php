<?php

namespace App\Models;

use App\Enums\EstadoPersonal;
use App\Enums\Rol;
use App\Support\Permisos;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'personal_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function rol(): ?Rol
    {
        return $this->personal?->rol;
    }

    public function hasRole(Rol ...$roles): bool
    {
        return in_array($this->rol(), $roles, true);
    }

    /** Master y Administrador tienen acceso total, como en el sistema legacy. */
    public function isAdmin(): bool
    {
        return $this->hasRole(Rol::Master, Rol::Administrador);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->personal?->estado === EstadoPersonal::Habilitado;
    }

    /**
     * Master y Administrador siempre tienen acceso completo. El resto de
     * roles se rige por la matriz de privilegios configurable (ver
     * App\Support\Permisos y la página Privilegios).
     */
    public function puede(string $clave): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $rol = $this->rol();

        return $rol && Permisos::tiene($rol, $clave);
    }
}
