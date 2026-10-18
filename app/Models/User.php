<?php

namespace App\Models;

use App\Enums\Role;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Utilisateur ; l'adresse e-mail doit être vérifiée pour signaler un incident ou contribuer (MustVerifyEmail). */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Organisations proposées dans le formulaire utilisateur (le personnel y est rattaché). */
    public const ORGANIZATIONS = [
        'Régie des eaux d\'Ariana',
        'Municipalité de Tunis',
        'Municipalité de Ben Arous',
    ];

    /** Disque et dossier des photos de profil : storage/app/public/avatars (php artisan storage:link). */
    public const AVATAR_DISK = 'public';

    public const AVATAR_DIRECTORY = 'avatars';

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
        'avatar_path',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
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

    // ---------------------------------------------------------------- Photo de profil

    protected static function booted(): void
    {
        // Pas de fichier orphelin : la photo est supprimée avec le compte
        static::deleted(fn (self $user) => $user->deleteAvatar());
    }

    /** URL publique de la photo de profil, ou null (les initiales sont alors affichées). */
    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk(self::AVATAR_DISK)->url($this->avatar_path) : null;
    }

    public function deleteAvatar(): void
    {
        if ($this->avatar_path) {
            Storage::disk(self::AVATAR_DISK)->delete($this->avatar_path);
        }
    }

    // ---------------------------------------------------------------- Authentification à deux facteurs

    /** 2FA active : secret généré et confirmé par un premier code TOTP. */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /** QR code SVG à scanner avec une application d'authentification (Google Authenticator, Authy…). */
    public function twoFactorQrCodeSvg(): string
    {
        $url = app(Google2FA::class)->getQRCodeUrl(config('app.name'), $this->email, $this->two_factor_secret);

        return (new Writer(new ImageRenderer(new RendererStyle(180, 1), new SvgImageBackEnd)))->writeString($url);
    }

    /** 8 nouveaux codes de récupération à usage unique (« A1B2C-D3E4F »). */
    public static function newRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** Consomme un code de récupération : true s'il était valide (il ne peut plus resservir). */
    public function useRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $code = Str::upper(trim($code));

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $this->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$code]))])->save();

        return true;
    }

    /** Recherche sur le nom ou l'e-mail. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")));
    }
}
