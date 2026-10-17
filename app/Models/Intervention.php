<?php

namespace App\Models;

use Database\Factories\InterventionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $screening_id
 * @property int $user_id
 * @property Carbon $tanggal_kunjungan
 * @property string $jenis_tindakan
 * @property string|null $deskripsi_tindakan
 * @property int $durasi_menit
 * @property float $biaya_operasional
 * @property bool $menggunakan_kb_modern
 * @property string|null $metode_kb
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Intervention extends Model
{
    /** @use HasFactory<InterventionFactory> */
    use HasFactory;

    protected $fillable = [
        'screening_id',
        'user_id',
        'tanggal_kunjungan',
        'jenis_tindakan',
        'deskripsi_tindakan',
        'durasi_menit',
        'biaya_operasional',
        'menggunakan_kb_modern',
        'metode_kb',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_kunjungan' => 'date',
            'biaya_operasional' => 'decimal:2',
            'menggunakan_kb_modern' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Screening, $this>
     */
    public function screening(): BelongsTo
    {
        return $this->belongsTo(Screening::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

        return $query->whereHas('screening.pusProfile', function (Builder $q) use ($user) {
            if ($user->desa_id) {
                $q->where('desa_id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $q->where('kecamatan_id', $user->kecamatan_id);
            }
        });
    }
}
