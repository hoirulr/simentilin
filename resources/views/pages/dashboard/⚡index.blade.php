<?php

use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Dashboard | SI-MENTILIN')] class extends Component
{
    #[Url(as: 'kec')]
    public ?int $kecamatan_id = null;

    #[Url(as: 'desa')]
    public ?int $desa_id = null;

    #[Url(as: 'start')]
    public ?string $start_date = null;

    #[Url(as: 'end')]
    public ?string $end_date = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && ! $user->isAdminDinas()) {
            $this->kecamatan_id = $user->kecamatan_id;
            if ($user->desa_id) {
                $this->desa_id = $user->desa_id;
            }
        }
    }

    public function updatedKecamatanId(): void
    {
        $this->desa_id = null;
    }

    public function resetFilters(): void
    {
        $user = auth()->user();
        if (! $user || $user->isAdminDinas()) {
            $this->kecamatan_id = null;
            $this->desa_id = null;
        } else {
            $this->desa_id = null;
        }

        $this->start_date = null;
        $this->end_date = null;
    }

    public function with(): array
    {
        $user = auth()->user();
        $isAdmin = ! $user || $user->isAdminDinas();

        // 1. Territory filter options
        $kecamatans = Kecamatan::orderBy('name')->get();
        $desas = $this->kecamatan_id
            ? Desa::where('kecamatan_id', $this->kecamatan_id)->orderBy('name')->get()
            : collect();

        // 2. Base PUS Query (unscoped – total registered)
        $pusQuery = PusProfile::query();
        if ($this->kecamatan_id) {
            $pusQuery->where('kecamatan_id', $this->kecamatan_id);
        }
        if ($this->desa_id) {
            $pusQuery->where('desa_id', $this->desa_id);
        }

        // 3. Base Screening Query with territory + date filters
        $screeningQuery = Screening::query()
            ->whereHas('pusProfile', function ($q) {
                if ($this->kecamatan_id) {
                    $q->where('kecamatan_id', $this->kecamatan_id);
                }
                if ($this->desa_id) {
                    $q->where('desa_id', $this->desa_id);
                }
            });

        if ($this->start_date) {
            $screeningQuery->where('tanggal_skrining', '>=', $this->start_date);
        }
        if ($this->end_date) {
            $screeningQuery->where('tanggal_skrining', '<=', $this->end_date);
        }

        // 4. PUS Summary Counters
        $totalPus    = (clone $pusQuery)->count();
        $totalScreened = (clone $screeningQuery)->distinct('pus_profile_id')->count('pus_profile_id');
        $totalBelumSkrining = $totalPus - $totalScreened;

        // 5. Blok MERAH — Skor 11-15
        $merahBase = (clone $screeningQuery)->where('total_skor', '>=', 11);
        $merahMupar       = (clone $merahBase)->where('is_mupar', true)->count();
        $merahNonMupar    = (clone $merahBase)->where('is_mupar', false)->count();
        $merahMuparSudah  = (clone $merahBase)->where('is_mupar', true)->whereHas('interventions')->count();
        $merahMuparBelum  = $merahMupar - $merahMuparSudah;
        $merahNonSudah    = (clone $merahBase)->where('is_mupar', false)->whereHas('interventions')->count();
        $merahNonBelum    = $merahNonMupar - $merahNonSudah;

        // 6. Blok KUNING — Skor 7-10
        $kuningBase = (clone $screeningQuery)->whereBetween('total_skor', [7, 10]);
        $kuningMupar       = (clone $kuningBase)->where('is_mupar', true)->count();
        $kuningNonMupar    = (clone $kuningBase)->where('is_mupar', false)->count();
        $kuningMuparSudah  = (clone $kuningBase)->where('is_mupar', true)->whereHas('interventions')->count();
        $kuningMuparBelum  = $kuningMupar - $kuningMuparSudah;
        $kuningNonSudah    = (clone $kuningBase)->where('is_mupar', false)->whereHas('interventions')->count();
        $kuningNonBelum    = $kuningNonMupar - $kuningNonSudah;

        // 7. Blok HIJAU — Skor 0-6
        $hijauBase = (clone $screeningQuery)->where('total_skor', '<=', 6);
        $hijauMupar       = (clone $hijauBase)->where('is_mupar', true)->count();
        $hijauNonMupar    = (clone $hijauBase)->where('is_mupar', false)->count();
        $hijauMuparSudah  = (clone $hijauBase)->where('is_mupar', true)->whereHas('interventions')->count();
        $hijauMuparBelum  = $hijauMupar - $hijauMuparSudah;
        $hijauNonSudah    = (clone $hijauBase)->where('is_mupar', false)->whereHas('interventions')->count();
        $hijauNonBelum    = $hijauNonMupar - $hijauNonSudah;

        // 8. Rekapitulasi per Kecamatan (full columns)
        $kecamatanStats = Kecamatan::orderBy('name')->get()->map(function ($kec) {
            $totalPusKec = PusProfile::where('kecamatan_id', $kec->id)->count();

            $screenKec = Screening::whereHas('pusProfile', fn ($q) => $q->where('kecamatan_id', $kec->id));

            $diskrining   = (clone $screenKec)->distinct('pus_profile_id')->count('pus_profile_id');
            $totalMupar   = (clone $screenKec)->where('is_mupar', true)->count();
            $totalNonMupar = (clone $screenKec)->where('is_mupar', false)->count();

            $muparSudah   = (clone $screenKec)->where('is_mupar', true)->whereHas('interventions')->count();
            $muparBelum   = $totalMupar - $muparSudah;
            $nonSudah     = (clone $screenKec)->where('is_mupar', false)->whereHas('interventions')->count();
            $nonBelum     = $totalNonMupar - $nonSudah;

            return [
                'id'            => $kec->id,
                'name'          => $kec->name,
                'total_pus'     => $totalPusKec,
                'diskrining'    => $diskrining,
                'belum_skrining'=> $totalPusKec - $diskrining,
                'total_mupar'   => $totalMupar,
                'total_non_mupar' => $totalNonMupar,
                'mupar_sudah'   => $muparSudah,
                'mupar_belum'   => $muparBelum,
                'non_sudah'     => $nonSudah,
                'non_belum'     => $nonBelum,
            ];
        });

        // 9. Recent Interventions
        $recentInterventions = Intervention::with(['screening.pusProfile.kecamatan', 'screening.pusProfile.desa', 'user'])
            ->latest('tanggal_kunjungan')
            ->take(5)
            ->get();

        return [
            'isAdmin'              => $isAdmin,
            'kecamatans'           => $kecamatans,
            'desas'                => $desas,
            'totalPus'             => $totalPus,
            'totalScreened'        => $totalScreened,
            'totalBelumSkrining'   => $totalBelumSkrining,
            // MERAH
            'merahMupar'           => $merahMupar,
            'merahNonMupar'        => $merahNonMupar,
            'merahMuparSudah'      => $merahMuparSudah,
            'merahMuparBelum'      => $merahMuparBelum,
            'merahNonSudah'        => $merahNonSudah,
            'merahNonBelum'        => $merahNonBelum,
            // KUNING
            'kuningMupar'          => $kuningMupar,
            'kuningNonMupar'       => $kuningNonMupar,
            'kuningMuparSudah'     => $kuningMuparSudah,
            'kuningMuparBelum'     => $kuningMuparBelum,
            'kuningNonSudah'       => $kuningNonSudah,
            'kuningNonBelum'       => $kuningNonBelum,
            // HIJAU
            'hijauMupar'           => $hijauMupar,
            'hijauNonMupar'        => $hijauNonMupar,
            'hijauMuparSudah'      => $hijauMuparSudah,
            'hijauMuparBelum'      => $hijauMuparBelum,
            'hijauNonSudah'        => $hijauNonSudah,
            'hijauNonBelum'        => $hijauNonBelum,
            // Table
            'kecamatanStats'       => $kecamatanStats,
            'recentInterventions'  => $recentInterventions,
        ];
    }
};
?>

<div class="space-y-8">

    {{-- ════════════════════════════════════════════════════════════
         TOP BANNER & EXECUTIVE HEADER
         ════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-[#008060] dark:text-emerald-400 text-xs font-semibold border border-emerald-200 dark:border-emerald-800/60">
                    <span class="w-2 h-2 rounded-full bg-[#008060] animate-pulse"></span>
                    <span>Dashboard Eksekutif Skrining PUS — DP2KBP3A Kabupaten Bangka</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    SI-MENTILIN: Skrining 5 Indikator &amp; Penjangkauan PUS
                </h2>
                <p class="text-slate-600 dark:text-slate-300 text-xs sm:text-sm leading-relaxed">
                    Identifikasi PUS berisiko tinggi dan Muda Paritas Rendah (MUPAR) untuk efisiensi intervensi dan peningkatan capaian KB di Kabupaten Bangka.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="/screening/priority-queue" wire:navigate
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-200 dark:hover:bg-slate-600 border border-slate-200 dark:border-slate-600 transition shadow-xs">
                    <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>Antrean Prioritas PUS</span>
                </a>
                <a href="/screening/create" wire:navigate
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold bg-[#008060] hover:bg-[#006e52] text-white transition shadow-md shadow-[#008060]/20">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Skrining Baru</span>
                </a>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         FILTER BAR
         ════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">

            <div class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                <svg class="w-4 h-4 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span>Filter Wilayah &amp; Periode</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 flex-1 lg:max-w-4xl">
                <div>
                    <label class="sr-only">Kecamatan</label>
                    <select wire:model.live="kecamatan_id"
                            @disabled(!$isAdmin)
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060] disabled:opacity-60">
                        <option value="">Semua Kecamatan (Kab. Bangka)</option>
                        @foreach($kecamatans as $kec)
                            <option value="{{ $kec->id }}">Kec. {{ $kec->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only">Desa / Kelurahan</label>
                    <select wire:model.live="desa_id"
                            @disabled(!$kecamatan_id)
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060] disabled:opacity-60">
                        <option value="">Semua Desa / Kelurahan</option>
                        @foreach($desas as $desa)
                            <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="sr-only">Mulai Tanggal</label>
                    <input type="date" wire:model.live="start_date"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                </div>
                <div>
                    <label class="sr-only">Sampai Tanggal</label>
                    <input type="date" wire:model.live="end_date"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                </div>
            </div>

            <div>
                <button type="button" wire:click="resetFilters"
                        class="w-full lg:w-auto px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 transition">
                    Reset
                </button>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SECTION 1: RINGKASAN DATA SASARAN & SKRINING (3 KPI CARDS)
         ════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">

        {{-- Total PUS --}}
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total PUS Terdaftar</p>
                <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($totalPus) }}</h3>
                <span class="inline-block text-[11px] text-slate-400 mt-1">Kabupaten Bangka</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
        </div>

        {{-- Sudah Diskrining --}}
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sudah Diskrining</p>
                <h3 class="text-3xl font-extrabold text-[#008060] dark:text-emerald-400 mt-1">{{ number_format($totalScreened) }}</h3>
                <span class="inline-block text-[11px] text-emerald-600 dark:text-emerald-400 mt-1">
                    {{ $totalPus > 0 ? round(($totalScreened / $totalPus) * 100, 1) : 0 }}% Cakupan Skrining
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

        {{-- Belum Diskrining --}}
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-amber-300 dark:border-amber-700/60 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Belum Diskrining</p>
                <h3 class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ number_format($totalBelumSkrining) }}</h3>
                <span class="inline-block text-[11px] text-amber-500 mt-1">
                    {{ $totalPus > 0 ? round(($totalBelumSkrining / $totalPus) * 100, 1) : 0 }}% Belum Ter-skrining
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>

    </div>

    {{-- ════════════════════════════════════════════════════════════
         SECTION 2: BLOK PRIORITAS BERWARNA (MERAH / KUNING / HIJAU)
         ════════════════════════════════════════════════════════════ --}}
    <div>
        <div class="flex items-center gap-2 mb-4">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Kotak Prioritas Berdasarkan Hasil Skrining</h3>
            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">Skor 0–15</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ── MERAH — PRIORITAS TINGGI (Skor 11–15) ── --}}
            <div class="rounded-3xl border-2 border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-950/30 overflow-hidden shadow-sm">
                {{-- Header --}}
                <div class="bg-red-600 dark:bg-red-800 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold text-red-100 uppercase tracking-widest">Skor 11 – 15</p>
                            <h4 class="text-white font-extrabold text-lg leading-tight">🔴 Prioritas Tinggi</h4>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black text-white">{{ number_format($merahMupar + $merahNonMupar) }}</p>
                            <p class="text-[10px] text-red-200">Total PUS</p>
                        </div>
                    </div>
                    <p class="text-red-100 text-[11px] mt-2 leading-relaxed">
                        Tindakan: Penjangkauan aktif &amp; konseling sesuai kebutuhan.
                    </p>
                </div>

                {{-- MUPAR Sub-card --}}
                <div class="p-5 space-y-3">
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-red-200 dark:border-red-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-red-700 dark:text-red-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-red-600 dark:bg-red-400"></span>
                                MUPAR
                            </span>
                            <span class="text-2xl font-black text-red-700 dark:text-red-400">{{ number_format($merahMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Prioritas Utama (#1) — Usia ≤35 thn &amp; Anak ≤2</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $merahMuparSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-red-50 dark:bg-red-950/40 p-2.5 text-center border border-red-200 dark:border-red-800/40">
                                <p class="text-lg font-black text-red-700 dark:text-red-400">{{ $merahMuparBelum }}</p>
                                <p class="text-[10px] text-red-600 dark:text-red-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>

                    {{-- Non-MUPAR Sub-card --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-red-200 dark:border-red-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                Non-MUPAR
                            </span>
                            <span class="text-2xl font-black text-slate-700 dark:text-slate-300">{{ number_format($merahNonMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Prioritas Tinggi (#2)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $merahNonSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-red-50 dark:bg-red-950/40 p-2.5 text-center border border-red-200 dark:border-red-800/40">
                                <p class="text-lg font-black text-red-700 dark:text-red-400">{{ $merahNonBelum }}</p>
                                <p class="text-[10px] text-red-600 dark:text-red-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── KUNING — PRIORITAS SEDANG (Skor 7–10) ── --}}
            <div class="rounded-3xl border-2 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/20 overflow-hidden shadow-sm">
                {{-- Header --}}
                <div class="bg-amber-500 dark:bg-amber-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold text-amber-100 uppercase tracking-widest">Skor 7 – 10</p>
                            <h4 class="text-white font-extrabold text-lg leading-tight">🟡 Prioritas Sedang</h4>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black text-white">{{ number_format($kuningMupar + $kuningNonMupar) }}</p>
                            <p class="text-[10px] text-amber-100">Total PUS</p>
                        </div>
                    </div>
                    <p class="text-amber-100 text-[11px] mt-2 leading-relaxed">
                        Tindakan: Penjangkauan &amp; konseling terjadwal. MUPAR mendapat edukasi sesuai kebutuhan.
                    </p>
                </div>

                <div class="p-5 space-y-3">
                    {{-- MUPAR --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-amber-200 dark:border-amber-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-amber-700 dark:text-amber-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-amber-500 dark:bg-amber-400"></span>
                                MUPAR
                            </span>
                            <span class="text-2xl font-black text-amber-700 dark:text-amber-400">{{ number_format($kuningMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Prioritas Edukasi Terjadwal (#3)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $kuningMuparSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 p-2.5 text-center border border-amber-200 dark:border-amber-800/40">
                                <p class="text-lg font-black text-amber-700 dark:text-amber-400">{{ $kuningMuparBelum }}</p>
                                <p class="text-[10px] text-amber-600 dark:text-amber-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>

                    {{-- Non-MUPAR --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-amber-200 dark:border-amber-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                Non-MUPAR
                            </span>
                            <span class="text-2xl font-black text-slate-700 dark:text-slate-300">{{ number_format($kuningNonMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Prioritas Sedang (#4)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $kuningNonSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/40 p-2.5 text-center border border-amber-200 dark:border-amber-800/40">
                                <p class="text-lg font-black text-amber-700 dark:text-amber-400">{{ $kuningNonBelum }}</p>
                                <p class="text-[10px] text-amber-600 dark:text-amber-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ── HIJAU — PRIORITAS RENDAH (Skor 0–6) ── --}}
            <div class="rounded-3xl border-2 border-emerald-300 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/20 overflow-hidden shadow-sm">
                {{-- Header --}}
                <div class="bg-[#008060] dark:bg-emerald-800 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-extrabold text-emerald-100 uppercase tracking-widest">Skor 0 – 6</p>
                            <h4 class="text-white font-extrabold text-lg leading-tight">🟢 Prioritas Rendah</h4>
                        </div>
                        <div class="text-right">
                            <p class="text-3xl font-black text-white">{{ number_format($hijauMupar + $hijauNonMupar) }}</p>
                            <p class="text-[10px] text-emerald-100">Total PUS</p>
                        </div>
                    </div>
                    <p class="text-emerald-100 text-[11px] mt-2 leading-relaxed">
                        Tindakan: Monitoring &amp; edukasi rutin sesuai kebutuhan.
                    </p>
                </div>

                <div class="p-5 space-y-3">
                    {{-- MUPAR --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-emerald-200 dark:border-emerald-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-emerald-700 dark:text-emerald-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-emerald-600 dark:bg-emerald-400"></span>
                                MUPAR
                            </span>
                            <span class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ number_format($hijauMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Monitoring / Edukasi Rutin (#5)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $hijauMuparSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-2.5 text-center border border-slate-200 dark:border-slate-600/40">
                                <p class="text-lg font-black text-slate-600 dark:text-slate-300">{{ $hijauMuparBelum }}</p>
                                <p class="text-[10px] text-slate-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>

                    {{-- Non-MUPAR --}}
                    <div class="bg-white dark:bg-slate-800 rounded-2xl p-4 border border-emerald-200 dark:border-emerald-800/50">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-extrabold text-slate-600 dark:text-slate-400 uppercase tracking-wide">
                                <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                Non-MUPAR
                            </span>
                            <span class="text-2xl font-black text-slate-700 dark:text-slate-300">{{ number_format($hijauNonMupar) }}</span>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-2">Monitoring / Edukasi Rutin (#5)</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/40 p-2.5 text-center border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-lg font-black text-emerald-700 dark:text-emerald-400">{{ $hijauNonSudah }}</p>
                                <p class="text-[10px] text-emerald-600 dark:text-emerald-500 font-semibold">Sudah Ditindak</p>
                            </div>
                            <div class="rounded-xl bg-slate-50 dark:bg-slate-700/40 p-2.5 text-center border border-slate-200 dark:border-slate-600/40">
                                <p class="text-lg font-black text-slate-600 dark:text-slate-300">{{ $hijauNonBelum }}</p>
                                <p class="text-[10px] text-slate-500 font-semibold">Belum Ditindak</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SECTION 3: REKAPITULASI PER KECAMATAN (TABEL LENGKAP)
         ════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">

        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    Rekapitulasi 8 Kecamatan Kabupaten Bangka
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Rincian total PUS, cakupan skrining, MUPAR/non-MUPAR, dan status kunjungan per kecamatan
                </p>
            </div>
            <a href="/laporan" wire:navigate
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow transition">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Lihat Laporan Lengkap
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50 dark:bg-slate-900/60 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-3 px-4 sticky left-0 bg-slate-50 dark:bg-slate-900/60 z-10">Kecamatan</th>
                        <th class="py-3 px-3 text-center">Total PUS</th>
                        <th class="py-3 px-3 text-center text-emerald-600 dark:text-emerald-400">Diskrining</th>
                        <th class="py-3 px-3 text-center text-amber-600 dark:text-amber-400">Blm Skrining</th>
                        <th class="py-3 px-3 text-center border-l border-slate-200 dark:border-slate-700 text-purple-600 dark:text-purple-400">MUPAR</th>
                        <th class="py-3 px-3 text-center text-emerald-600 dark:text-emerald-400">MUPAR Sdh Kunjung</th>
                        <th class="py-3 px-3 text-center text-red-600 dark:text-red-400">MUPAR Blm Kunjung</th>
                        <th class="py-3 px-3 text-center border-l border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400">Non-MUPAR</th>
                        <th class="py-3 px-3 text-center text-emerald-600 dark:text-emerald-400">Non-MUPAR Sdh TL</th>
                        <th class="py-3 px-3 text-center text-red-600 dark:text-red-400">Non-MUPAR Blm TL</th>
                        <th class="py-3 px-4 text-right">Detail</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($kecamatanStats as $stat)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white sticky left-0 bg-white dark:bg-slate-800 z-10">
                                Kec. {{ $stat['name'] }}
                            </td>
                            <td class="py-3 px-3 text-center font-semibold text-slate-700 dark:text-slate-300">
                                {{ number_format($stat['total_pus']) }}
                            </td>
                            <td class="py-3 px-3 text-center font-bold text-emerald-700 dark:text-emerald-400">
                                {{ number_format($stat['diskrining']) }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($stat['belum_skrining'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300">
                                        {{ $stat['belum_skrining'] }}
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓ Selesai</span>
                                @endif
                            </td>
                            {{-- MUPAR columns --}}
                            <td class="py-3 px-3 text-center font-bold text-purple-700 dark:text-purple-400 border-l border-slate-100 dark:border-slate-700/40">
                                {{ number_format($stat['total_mupar']) }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ $stat['mupar_sudah'] }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($stat['mupar_belum'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                                        {{ $stat['mupar_belum'] }}
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                @endif
                            </td>
                            {{-- Non-MUPAR columns --}}
                            <td class="py-3 px-3 text-center font-bold text-slate-600 dark:text-slate-400 border-l border-slate-100 dark:border-slate-700/40">
                                {{ number_format($stat['total_non_mupar']) }}
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                    {{ $stat['non_sudah'] }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($stat['non_belum'] > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                                        {{ $stat['non_belum'] }}
                                    </span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="/laporan?kec={{ $stat['id'] }}" wire:navigate
                                   class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#008060] dark:text-emerald-400 hover:underline">
                                    <span>Laporan</span>
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-slate-400 text-xs">
                                Belum ada data kecamatan tersedia.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                {{-- Footer: Total Row --}}
                @if($kecamatanStats->count() > 0)
                    <tfoot class="bg-slate-100 dark:bg-slate-900 font-bold border-t-2 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white text-xs">
                        <tr>
                            <td class="py-3.5 px-4 uppercase tracking-wider text-[11px]">Total Kab. Bangka</td>
                            <td class="py-3.5 px-3 text-center">{{ number_format($kecamatanStats->sum('total_pus')) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600 dark:text-emerald-400">{{ number_format($kecamatanStats->sum('diskrining')) }}</td>
                            <td class="py-3.5 px-3 text-center text-amber-600 dark:text-amber-400">{{ number_format($kecamatanStats->sum('belum_skrining')) }}</td>
                            <td class="py-3.5 px-3 text-center text-purple-600 dark:text-purple-400 border-l border-slate-200 dark:border-slate-700">{{ number_format($kecamatanStats->sum('total_mupar')) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600 dark:text-emerald-400">{{ number_format($kecamatanStats->sum('mupar_sudah')) }}</td>
                            <td class="py-3.5 px-3 text-center text-red-600 dark:text-red-400">{{ number_format($kecamatanStats->sum('mupar_belum')) }}</td>
                            <td class="py-3.5 px-3 text-center border-l border-slate-200 dark:border-slate-700">{{ number_format($kecamatanStats->sum('total_non_mupar')) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600 dark:text-emerald-400">{{ number_format($kecamatanStats->sum('non_sudah')) }}</td>
                            <td class="py-3.5 px-3 text-center text-red-600 dark:text-red-400">{{ number_format($kecamatanStats->sum('non_belum')) }}</td>
                            <td class="py-3.5 px-4"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SECTION 4: AKTIVITAS INTERVENSI TERAKHIR
         ════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Aktivitas Intervensi Terakhir</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">5 catatan kunjungan / konseling terbaru</p>
            </div>
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
            @forelse($recentInterventions as $item)
                <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                            {{ $item->screening->pusProfile->nama_istri }}
                        </span>
                        <span class="text-[10px] text-slate-400 shrink-0 ml-1">
                            {{ $item->tanggal_kunjungan->format('d M') }}
                        </span>
                    </div>
                    <p class="text-[11px] text-[#008060] dark:text-emerald-400 font-medium truncate">{{ $item->jenis_tindakan }}</p>
                    <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                        {{ $item->screening->pusProfile->kecamatan?->name }}
                    </p>
                    <span class="inline-block mt-1.5 text-[10px] font-semibold px-2 py-0.5 rounded-full {{ $item->menggunakan_kb_modern ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400' }}">
                        {{ $item->menggunakan_kb_modern ? 'KB Modern' : 'Belum Ber-KB' }}
                    </span>
                </div>
            @empty
                <div class="col-span-full py-8 text-center text-slate-400 text-xs">
                    <p>Belum ada catatan intervensi lapangan.</p>
                    <p class="text-[10px] mt-1">Buka Antrean Prioritas untuk mencatat kunjungan.</p>
                </div>
            @endforelse
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 mt-4">
            <a href="/screening/priority-queue" wire:navigate
               class="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-xs font-bold text-white bg-[#008060] hover:bg-[#006e52] transition">
                <span>Mulai Catat Intervensi Baru</span>
            </a>
        </div>
    </div>

    {{-- Livewire 4 Modal Pencatatan Intervensi --}}
    <livewire:intervention-modal />

</div>
