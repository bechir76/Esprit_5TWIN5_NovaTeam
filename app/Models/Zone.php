<?php

namespace App\Models;

use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Zone de desserte (secteur d'une commune) qui regroupe des infrastructures. */
class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    /** Gouvernorats proposés dans le formulaire => abréviation utilisée dans les codes ouvrage (RES-ARI-004). */
    public const GOVERNORATES = [
        'Tunis' => 'TUN',
        'Ariana' => 'ARI',
        'Ben Arous' => 'BAR',
        'Manouba' => 'MAN',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'municipality',
        'governorate',
        'population_served',
        'description',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'population_served' => 'integer',
        'maintenance_plan' => 'array',
        'maintenance_plan_at' => 'datetime',
    ];

    /** @return HasMany<Infrastructure, $this> */
    public function infrastructures(): HasMany
    {
        return $this->hasMany(Infrastructure::class);
    }

    /** Abréviation du gouvernorat (milieu du code ouvrage). */
    public function governorateCode(): string
    {
        return self::GOVERNORATES[$this->governorate]
            ?? Str::upper(Str::substr(preg_replace('/[^A-Za-z]/', '', Str::ascii($this->governorate)), 0, 3));
    }

    /** Recherche par nom, commune ou gouvernorat. */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $q) => $q->where(fn (Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('municipality', 'like', "%{$term}%")
            ->orWhere('governorate', 'like', "%{$term}%")));
    }

    /** @return array<int, string> id => nom, pour <x-form.select> et les filtres */
    public static function options(): array
    {
        return self::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
