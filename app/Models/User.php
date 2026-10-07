<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Organisations proposées dans le formulaire utilisateur (le personnel y est rattaché). */
    public const ORGANIZATIONS = [
        'Régie des eaux d\'Ariana',
        'Municipalité de Tunis',
        'Municipalité de Ben Arous',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'organization',
        'address',
        'is_active',
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
     * Valeurs par défaut, identiques à celles de la migration.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'citoyen',
        'is_active' => true,
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
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Incidents signalés par l'utilisateur (« Mes signalements »).
     *
     * @return HasMany<Incident, $this>
     */
    public function reportedIncidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Interventions assignées au technicien.
     *
     * @return HasMany<Intervention, $this>
     */
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class, 'technician_id');
    }

    /**
     * Contributions du citoyen aux projets (« Mes contributions »).
     *
     * @return HasMany<Funding, $this>
     */
    public function fundings(): HasMany
    {
        return $this->hasMany(Funding::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Personnel du back office : admin, gestionnaire ou technicien. */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /** Initiales affichées dans les avatars (« Karim Trabelsi » → « KT »). */
    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
    }

    /** Recherche sur le nom ou l'e-mail. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")));
    }
}
