<?php

namespace App\Models;

use Database\Factories\KecamatanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Kecamatan extends Model
{
    /** @use HasFactory<KecamatanFactory> */
    use HasFactory;

    protected $fillable = ['name', 'code'];

    /**
     * @return HasMany<Desa, $this>
     */
    public function desas(): HasMany
    {
        return $this->hasMany(Desa::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<PusProfile, $this>
     */
    public function pusProfiles(): HasMany
    {
        return $this->hasMany(PusProfile::class);
    }

    /**
     * @return HasManyThrough<Screening, PusProfile, $this>
     */
    public function screenings(): HasManyThrough
    {
        return $this->hasManyThrough(Screening::class, PusProfile::class);
    }
}
