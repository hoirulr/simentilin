<?php

namespace App\Services;

use App\Models\Intervention;
use App\Models\Screening;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Service for calculating PIM III Economic Value Indicators.
 *
 * Implements the 4 economic metrics from PRD §3.3:
 * 1. Persentase Ketepatan Sasaran (%)
 * 2. Biaya per PUS Prioritas Terjangkau (Rp)
 * 3. Efisiensi Waktu Identifikasi (Menit)
 * 4. Konversi Keberhasilan KB Modern / Kontribusi MCPR (%)
 */
class EconomicMetricsService
{
    /**
     * Calculate targeting accuracy percentage.
     *
     * Formula: (PUS Prioritas [Skor ≥ 7] yang Dijangkau ÷ Seluruh PUS yang Dijangkau) × 100%
     *
     * "Dijangkau" means the screening has at least one intervention recorded.
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     */
    public function ketepatanSasaran(array $filters = []): float
    {
        $query = $this->baseInterventionQuery($filters);

        $totalReached = (clone $query)->distinct('screenings.pus_profile_id')->count('screenings.pus_profile_id');

        if ($totalReached === 0) {
            return 0.0;
        }

        $priorityReached = (clone $query)
            ->where('screenings.total_skor', '>=', 7)
            ->distinct('screenings.pus_profile_id')
            ->count('screenings.pus_profile_id');

        return round(($priorityReached / $totalReached) * 100, 2);
    }

    /**
     * Calculate cost per priority PUS reached.
     *
     * Formula: Total Biaya Operasional Penjangkauan ÷ Jumlah PUS Prioritas yang Berhasil Dijangkau
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     */
    public function biayaPerPusPrioritas(array $filters = []): float
    {
        $query = $this->baseInterventionQuery($filters)
            ->where('screenings.total_skor', '>=', 7);

        $totalCost = (clone $query)->sum('interventions.biaya_operasional');
        $priorityReached = (clone $query)->distinct('screenings.pus_profile_id')->count('screenings.pus_profile_id');

        if ($priorityReached === 0) {
            return 0.0;
        }

        return round((float) $totalCost / $priorityReached, 2);
    }

    /**
     * Calculate average identification time efficiency.
     *
     * Returns the average duration (in minutes) across all interventions.
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     */
    public function efisiensiWaktu(array $filters = []): float
    {
        $avgDuration = $this->baseInterventionQuery($filters)
            ->avg('interventions.durasi_menit');

        return round((float) ($avgDuration ?? 0), 2);
    }

    /**
     * Calculate modern contraceptive conversion rate (MCPR contribution).
     *
     * Formula: (PUS Terjangkau yang Menggunakan KB Modern ÷ Total PUS Terjangkau) × 100%
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     */
    public function konversiKbModern(array $filters = []): float
    {
        $query = $this->baseInterventionQuery($filters);

        $totalReached = (clone $query)->distinct('screenings.pus_profile_id')->count('screenings.pus_profile_id');

        if ($totalReached === 0) {
            return 0.0;
        }

        $kbModernCount = (clone $query)
            ->where('interventions.menggunakan_kb_modern', true)
            ->distinct('screenings.pus_profile_id')
            ->count('screenings.pus_profile_id');

        return round(($kbModernCount / $totalReached) * 100, 2);
    }

    /**
     * Get all 4 economic metrics at once.
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     * @return array{
     *     ketepatan_sasaran: float,
     *     biaya_per_pus_prioritas: float,
     *     efisiensi_waktu: float,
     *     konversi_kb_modern: float
     * }
     */
    public function allMetrics(array $filters = []): array
    {
        return [
            'ketepatan_sasaran' => $this->ketepatanSasaran($filters),
            'biaya_per_pus_prioritas' => $this->biayaPerPusPrioritas($filters),
            'efisiensi_waktu' => $this->efisiensiWaktu($filters),
            'konversi_kb_modern' => $this->konversiKbModern($filters),
        ];
    }

    /**
     * Build the base query joining interventions with screenings and pus_profiles.
     *
     * @param  array{kecamatan_id?: int, desa_id?: int, start_date?: string, end_date?: string}  $filters
     * @return Builder
     */
    private function baseInterventionQuery(array $filters = [])
    {
        $query = DB::table('interventions')
            ->join('screenings', 'interventions.screening_id', '=', 'screenings.id')
            ->join('pus_profiles', 'screenings.pus_profile_id', '=', 'pus_profiles.id');

        if (! empty($filters['kecamatan_id'])) {
            $query->where('pus_profiles.kecamatan_id', $filters['kecamatan_id']);
        }

        if (! empty($filters['desa_id'])) {
            $query->where('pus_profiles.desa_id', $filters['desa_id']);
        }

        if (! empty($filters['start_date'])) {
            $query->where('interventions.tanggal_kunjungan', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->where('interventions.tanggal_kunjungan', '<=', $filters['end_date']);
        }

        return $query;
    }
}
