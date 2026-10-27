<?php

namespace App\Services;

use App\Models\Kecamatan;
use App\Models\PusProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardMetricsService
{
    /**
     * Get aggregated metrics for dashboard priority blocks and summary counters.
     *
     * @return array<string, mixed>
     */
    public function getSummaryAndBlocks(?int $kecamatanId = null, ?int $desaId = null, ?string $startDate = null, ?string $endDate = null): array
    {
        $pusQuery = PusProfile::query();
        if ($kecamatanId) {
            $pusQuery->where('kecamatan_id', $kecamatanId);
        }
        if ($desaId) {
            $pusQuery->where('desa_id', $desaId);
        }
        $totalPus = $pusQuery->count();

        $metrics = DB::table('screenings')
            ->join('pus_profiles', 'screenings.pus_profile_id', '=', 'pus_profiles.id')
            ->when($kecamatanId, fn ($q) => $q->where('pus_profiles.kecamatan_id', $kecamatanId))
            ->when($desaId, fn ($q) => $q->where('pus_profiles.desa_id', $desaId))
            ->when($startDate, fn ($q) => $q->where('screenings.tanggal_skrining', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('screenings.tanggal_skrining', '<=', $endDate))
            ->selectRaw("
                count(distinct screenings.pus_profile_id) as total_screened,

                -- MERAH (>= 11)
                count(case when screenings.total_skor >= 11 and screenings.is_mupar = 1 then 1 end) as merah_mupar,
                count(case when screenings.total_skor >= 11 and screenings.is_mupar = 0 then 1 end) as merah_non_mupar,
                count(case when screenings.total_skor >= 11 and screenings.is_mupar = 1 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as merah_mupar_sudah,
                count(case when screenings.total_skor >= 11 and screenings.is_mupar = 0 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as merah_non_sudah,

                -- KUNING (7-10)
                count(case when screenings.total_skor between 7 and 10 and screenings.is_mupar = 1 then 1 end) as kuning_mupar,
                count(case when screenings.total_skor between 7 and 10 and screenings.is_mupar = 0 then 1 end) as kuning_non_mupar,
                count(case when screenings.total_skor between 7 and 10 and screenings.is_mupar = 1 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as kuning_mupar_sudah,
                count(case when screenings.total_skor between 7 and 10 and screenings.is_mupar = 0 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as kuning_non_sudah,

                -- HIJAU (<= 6)
                count(case when screenings.total_skor <= 6 and screenings.is_mupar = 1 then 1 end) as hijau_mupar,
                count(case when screenings.total_skor <= 6 and screenings.is_mupar = 0 then 1 end) as hijau_non_mupar,
                count(case when screenings.total_skor <= 6 and screenings.is_mupar = 1 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as hijau_mupar_sudah,
                count(case when screenings.total_skor <= 6 and screenings.is_mupar = 0 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as hijau_non_sudah
            ")
            ->first();

        $totalScreened = (int) ($metrics->total_screened ?? 0);
        $totalBelumSkrining = max(0, $totalPus - $totalScreened);

        $merahMupar = (int) ($metrics->merah_mupar ?? 0);
        $merahNonMupar = (int) ($metrics->merah_non_mupar ?? 0);
        $merahMuparSudah = (int) ($metrics->merah_mupar_sudah ?? 0);
        $merahMuparBelum = max(0, $merahMupar - $merahMuparSudah);
        $merahNonSudah = (int) ($metrics->merah_non_sudah ?? 0);
        $merahNonBelum = max(0, $merahNonMupar - $merahNonSudah);

        $kuningMupar = (int) ($metrics->kuning_mupar ?? 0);
        $kuningNonMupar = (int) ($metrics->kuning_non_mupar ?? 0);
        $kuningMuparSudah = (int) ($metrics->kuning_mupar_sudah ?? 0);
        $kuningMuparBelum = max(0, $kuningMupar - $kuningMuparSudah);
        $kuningNonSudah = (int) ($metrics->kuning_non_sudah ?? 0);
        $kuningNonBelum = max(0, $kuningNonMupar - $kuningNonSudah);

        $hijauMupar = (int) ($metrics->hijau_mupar ?? 0);
        $hijauNonMupar = (int) ($metrics->hijau_non_mupar ?? 0);
        $hijauMuparSudah = (int) ($metrics->hijau_mupar_sudah ?? 0);
        $hijauMuparBelum = max(0, $hijauMupar - $hijauMuparSudah);
        $hijauNonSudah = (int) ($metrics->hijau_non_sudah ?? 0);
        $hijauNonBelum = max(0, $hijauNonMupar - $hijauNonSudah);

        return [
            'totalPus' => $totalPus,
            'totalScreened' => $totalScreened,
            'totalBelumSkrining' => $totalBelumSkrining,
            // MERAH
            'merahMupar' => $merahMupar,
            'merahNonMupar' => $merahNonMupar,
            'merahMuparSudah' => $merahMuparSudah,
            'merahMuparBelum' => $merahMuparBelum,
            'merahNonSudah' => $merahNonSudah,
            'merahNonBelum' => $merahNonBelum,
            // KUNING
            'kuningMupar' => $kuningMupar,
            'kuningNonMupar' => $kuningNonMupar,
            'kuningMuparSudah' => $kuningMuparSudah,
            'kuningMuparBelum' => $kuningMuparBelum,
            'kuningNonSudah' => $kuningNonSudah,
            'kuningNonBelum' => $kuningNonBelum,
            // HIJAU
            'hijauMupar' => $hijauMupar,
            'hijauNonMupar' => $hijauNonMupar,
            'hijauMuparSudah' => $hijauMuparSudah,
            'hijauMuparBelum' => $hijauMuparBelum,
            'hijauNonSudah' => $hijauNonSudah,
            'hijauNonBelum' => $hijauNonBelum,
        ];
    }

    /**
     * Get district recapitulation table data optimized into 2 queries (eliminating N+1).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getKecamatanStats(): Collection
    {
        $pusPerKec = PusProfile::selectRaw('kecamatan_id, count(*) as total_pus')
            ->groupBy('kecamatan_id')
            ->pluck('total_pus', 'kecamatan_id');

        $screeningPerKec = DB::table('screenings')
            ->join('pus_profiles', 'screenings.pus_profile_id', '=', 'pus_profiles.id')
            ->selectRaw("
                pus_profiles.kecamatan_id,
                count(distinct screenings.pus_profile_id) as diskrining,
                count(case when screenings.is_mupar = 1 then 1 end) as total_mupar,
                count(case when screenings.is_mupar = 0 then 1 end) as total_non_mupar,
                count(case when screenings.is_mupar = 1 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as mupar_sudah,
                count(case when screenings.is_mupar = 0 and exists (select 1 from interventions where interventions.screening_id = screenings.id) then 1 end) as non_sudah
            ")
            ->groupBy('pus_profiles.kecamatan_id')
            ->get()
            ->keyBy('kecamatan_id');

        return Kecamatan::orderBy('name')->get()->map(function ($kec) use ($pusPerKec, $screeningPerKec) {
            $totalPusKec = (int) ($pusPerKec[$kec->id] ?? 0);
            $stat = $screeningPerKec[$kec->id] ?? null;

            $diskrining = (int) ($stat->diskrining ?? 0);
            $totalMupar = (int) ($stat->total_mupar ?? 0);
            $totalNonMupar = (int) ($stat->total_non_mupar ?? 0);
            $muparSudah = (int) ($stat->mupar_sudah ?? 0);
            $muparBelum = max(0, $totalMupar - $muparSudah);
            $nonSudah = (int) ($stat->non_sudah ?? 0);
            $nonBelum = max(0, $totalNonMupar - $nonSudah);

            return [
                'id' => $kec->id,
                'name' => $kec->name,
                'total_pus' => $totalPusKec,
                'diskrining' => $diskrining,
                'belum_skrining' => max(0, $totalPusKec - $diskrining),
                'total_mupar' => $totalMupar,
                'total_non_mupar' => $totalNonMupar,
                'mupar_sudah' => $muparSudah,
                'mupar_belum' => $muparBelum,
                'non_sudah' => $nonSudah,
                'non_belum' => $nonBelum,
            ];
        });
    }
}
