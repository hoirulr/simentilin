<?php

namespace App\Models;

use Database\Factories\PusProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $kecamatan_id
 * @property int $desa_id
 * @property string $nama_istri
 * @property string $nama_suami
 * @property Carbon $tanggal_lahir_istri
 * @property int $jumlah_anak_lahir_hidup
 * @property string|null $rt_rw
 * @property string|null $alamat
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PusProfile extends Model
{
    /** @use HasFactory<PusProfileFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'kecamatan_id',
        'desa_id',
        'nama_istri',
        'nama_suami',
        'tanggal_lahir_istri',
        'jumlah_anak_lahir_hidup',
        'rt_rw',
        'alamat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_lahir_istri' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Kecamatan, $this>
     */
    public function kecamatan(): BelongsTo
    {
        return $this->belongsTo(Kecamatan::class);
    }

    /**
     * @return BelongsTo<Desa, $this>
     */
    public function desa(): BelongsTo
    {
        return $this->belongsTo(Desa::class);
    }

    /**
     * @return HasMany<Screening, $this>
     */
    public function screenings(): HasMany
    {
        return $this->hasMany(Screening::class);
    }

    /**
     * Get the latest screening for this PUS.
     *
     * @return HasMany<Screening, $this>
     */
    public function latestScreening(): HasMany
    {
        return $this->screenings()->latest('tanggal_skrining')->limit(1);
    }

    /**
     * Calculate the wife's age based on tanggal_lahir_istri.
     */
    public function usiaIstri(?Carbon $referenceDate = null): int
    {
        $reference = $referenceDate ?? now();

        return (int) $this->tanggal_lahir_istri->diffInYears($reference);
    }

    /**
     * Scope query to current user's assigned territory.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForCurrentUser(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user || $user->isAdminDinas()) {
            return $query;
        }

        if ($user->desa_id) {
            return $query->where('desa_id', $user->desa_id);
        }

        if ($user->kecamatan_id) {
            return $query->where('kecamatan_id', $user->kecamatan_id);
        }

        return $query;
    }
}
