<?php

namespace App\Models;

use App\Enums\PriorityCategory;
use Database\Factories\ScreeningFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pus_profile_id
 * @property int $user_id
 * @property int $p1_usia_istri
 * @property int $p2_jumlah_anak
 * @property int $p3_status_kontrasepsi
 * @property int $p4_tujuan_reproduksi
 * @property int $p5_risiko_kehamilan
 * @property int $total_skor
 * @property string $kategori
 * @property bool $is_mupar
 * @property string $prioritas_akhir
 * @property int $urutan_antrean
 * @property Carbon $tanggal_skrining
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Screening extends Model
{
    /** @use HasFactory<ScreeningFactory> */
    use HasFactory;

    protected $fillable = [
        'pus_profile_id',
        'user_id',
        'p1_usia_istri',
        'p2_jumlah_anak',
        'p3_status_kontrasepsi',
        'p4_tujuan_reproduksi',
        'p5_risiko_kehamilan',
        'total_skor',
        'kategori',
        'is_mupar',
        'prioritas_akhir',
        'urutan_antrean',
        'tanggal_skrining',
        'catatan',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_mupar' => 'boolean',
            'tanggal_skrining' => 'date',
            'kategori' => PriorityCategory::class,
        ];
    }

    /**
     * @return BelongsTo<PusProfile, $this>
     */
    public function pusProfile(): BelongsTo
    {
        return $this->belongsTo(PusProfile::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Intervention, $this>
     */
    public function interventions(): HasMany
    {
        return $this->hasMany(Intervention::class);
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

        return $query->whereHas('pusProfile', function (Builder $q) use ($user) {
            if ($user->desa_id) {
                $q->where('desa_id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $q->where('kecamatan_id', $user->kecamatan_id);
            }
        });
    }
}
