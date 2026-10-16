<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kecamatan_id
 * @property string $name
 * @property string $code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Desa extends Model
{
    protected $fillable = ['kecamatan_id', 'name', 'code'];

    /**
     * @return BelongsTo<Kecamatan, $this>
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }

    /**
     * @return HasMany<PusProfile, $this>
     */
    public function pusProfiles(): HasMany
    {
        return $this->hasMany(PusProfile::class);
    }
}
