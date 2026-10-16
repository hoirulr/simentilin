<?php

use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Services\EconomicMetricsService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Dashboard Eksekutif | SI-MENTILIN')] class extends Component
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

        // 2. Base Query Scopes
        $pusQuery = PusProfile::query();
        $screeningQuery = Screening::query();
        $interventionQuery = Intervention::query()
            ->join('screenings', 'interventions.screening_id', '=', 'screenings.id')
            ->join('pus_profiles', 'screenings.pus_profile_id', '=', 'pus_profiles.id');

        if ($this->kecamatan_id) {
            $pusQuery->where('kecamatan_id', $this->kecamatan_id);
            $screeningQuery->whereHas('pusProfile', fn ($q) => $q->where('kecamatan_id', $this->kecamatan_id));
            $interventionQuery->where('pus_profiles.kecamatan_id', $this->kecamatan_id);
        }

        if ($this->desa_id) {
            $pusQuery->where('desa_id', $this->desa_id);
            $screeningQuery->whereHas('pusProfile', fn ($q) => $q->where('desa_id', $this->desa_id));
            $interventionQuery->where('pus_profiles.desa_id', $this->desa_id);
        }

        if ($this->start_date) {
            $screeningQuery->where('tanggal_skrining', '>=', $this->start_date);
            $interventionQuery->where('interventions.tanggal_kunjungan', '>=', $this->start_date);
        }

        if ($this->end_date) {
            $screeningQuery->where('tanggal_skrining', '<=', $this->end_date);
            $interventionQuery->where('interventions.tanggal_kunjungan', '<=', $this->end_date);
        }

        // Summary Counters
        $totalPus = (clone $pusQuery)->count();
        $totalScreened = (clone $screeningQuery)->distinct('pus_profile_id')->count('pus_profile_id');
        $prioritasUtamaCount = (clone $screeningQuery)->where('urutan_antrean', 1)->count();
        $prioritasTinggiCount = (clone $screeningQuery)->where('urutan_antrean', 2)->count();
        $totalInterventions = (clone $interventionQuery)->count();

        // Distribution by Kecamatan (for administrative overview)
        $kecamatanStats = Kecamatan::withCount([
            'pusProfiles',
            'screenings as prioritas_utama_count' => fn ($q) => $q->where('urutan_antrean', 1),
            'screenings as prioritas_tinggi_count' => fn ($q) => $q->where('urutan_antrean', 2),
        ])->get();

        // Recent Interventions list
        $recentInterventions = Intervention::with(['screening.pusProfile.kecamatan', 'screening.pusProfile.desa', 'user'])
            ->latest('tanggal_kunjungan')
            ->take(5)
            ->get();

        return [
            'isAdmin' => $isAdmin,
            'kecamatans' => $kecamatans,
            'desas' => $desas,
            'totalPus' => $totalPus,
            'totalScreened' => $totalScreened,
            'prioritasUtamaCount' => $prioritasUtamaCount,
            'prioritasTinggiCount' => $prioritasTinggiCount,
            'totalInterventions' => $totalInterventions,
            'kecamatanStats' => $kecamatanStats,
            'recentInterventions' => $recentInterventions,
        ];
    }
};
?>

<div class="space-y-8">

    <!-- ════════════════════════════════════════════════════════════
         TOP BANNER & EXECUTIVE HEADER (TailAdmin Light Mode)
         ════════════════════════════════════════════════════════════ -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 text-slate-800 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-[#008060] dark:text-emerald-400 text-xs font-semibold border border-emerald-200 dark:border-emerald-800/60">
                    <span class="w-2 h-2 rounded-full bg-[#008060] animate-pulse"></span>
                    <span>Dashboard Eksekutif DP2KBP3A Kabupaten Bangka</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    Inovasi SI-MENTILIN: Skrining 5 Indikator &amp; PIM III
                </h2>
                <p class="text-slate-600 dark:text-slate-300 text-xs sm:text-sm leading-relaxed">
                    Identifikasi cepat Pasangan Usia Subur (PUS) berisiko tinggi dan Muda Paritas Rendah (MUPAR) untuk efisiensi intervensi dan peningkatan capaian Modern Contraceptive Prevalence Rate (MCPR).
                </p>
            </div>

            <!-- Quick Action Links -->
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

    <!-- ════════════════════════════════════════════════════════════
         FILTER BAR (TailAdmin Glassmorphism Territory Selector)
         ════════════════════════════════════════════════════════════ -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl p-5 shadow-sm border border-slate-200 dark:border-slate-700">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            
            <div class="flex items-center gap-2 text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider">
                <svg class="w-4 h-4 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                </svg>
                <span>Filter Wilayah &amp; Periode</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3 flex-1 lg:max-w-4xl">
                
                <!-- Kecamatan Filter -->
                <div>
                    <label class="sr-only">Kecamatan</label>
                    <select wire:model.live="kecamatan_id"
                            @disabled(! $isAdmin)
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060] disabled:opacity-60">
                        <option value="">Semua Kecamatan (Kab. Bangka)</option>
                        @foreach($kecamatans as $kec)
                            <option value="{{ $kec->id }}">Kec. {{ $kec->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Desa Filter -->
                <div>
                    <label class="sr-only">Desa / Kelurahan</label>
                    <select wire:model.live="desa_id"
                            @disabled(! $kecamatan_id)
                            class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060] disabled:opacity-60">
                        <option value="">Semua Desa / Kelurahan</option>
                        @foreach($desas as $desa)
                            <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Start Date -->
                <div>
                    <label class="sr-only">Mulai Tanggal</label>
                    <input type="date"
                           wire:model.live="start_date"
                           placeholder="Mulai Tanggal"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                </div>

                <!-- End Date -->
                <div>
                    <label class="sr-only">Sampai Tanggal</label>
                    <input type="date"
                           wire:model.live="end_date"
                           placeholder="Sampai Tanggal"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                </div>

            </div>

            <!-- Reset Filter Button -->
            <div>
                <button type="button"
                        wire:click="resetFilters"
                        class="w-full lg:w-auto px-3.5 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 transition">
                    Reset
                </button>
            </div>

        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         SECTION 1: RINGKASAN DATA SASARAN & SKRINING (KPI CARDS)
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        
        <!-- Total PUS Binaan -->
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Total PUS Binaan
                </p>
                <h3 class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">
                    {{ number_format($totalPus) }}
                </h3>
                <span class="inline-block text-[11px] text-slate-400 mt-1">
                    Target Wilayah Terdata
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>

        <!-- Total Ter-skrining -->
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    PUS Ter-skrining
                </p>
                <h3 class="text-3xl font-extrabold text-[#008060] dark:text-emerald-400 mt-1">
                    {{ number_format($totalScreened) }}
                </h3>
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

        <!-- PUS Prioritas Utama (#1 Skor 11-15 + MUPAR) -->
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider flex items-center gap-1">
                    <span>Prioritas Utama</span>
                    <span class="text-[10px] px-1 py-0.2 rounded bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">#1</span>
                </p>
                <h3 class="text-3xl font-extrabold text-red-600 dark:text-red-400 mt-1">
                    {{ number_format($prioritasUtamaCount) }}
                </h3>
                <span class="inline-block text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    Skor 11–15 &amp; Flag MUPAR
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
        </div>

        <!-- Realisasi Intervensi Selesai -->
        <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                    Realisasi Intervensi
                </p>
                <h3 class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">
                    {{ number_format($totalInterventions) }}
                </h3>
                <span class="inline-block text-[11px] text-slate-400 mt-1">
                    Kunjungan / KIE Tercatat
                </span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         SECTION 2: INDIKATOR NILAI EKONOMIS INOVASI (PIM III)
         Fitur Livewire 4 @island(lazy: true) dan @placeholder
         ════════════════════════════════════════════════════════════ -->
    <div class="space-y-4">
        
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        Indikator Nilai Ekonomis Inovasi (PIM III)
                    </h3>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                        ROI Inovasi
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Metrik efisiensi biaya, ketepatan sasaran, dan efisiensi waktu penjangkauan (PRD §3.3)
                </p>
            </div>

            <div class="hidden sm:flex items-center gap-1.5 text-xs text-[#008060] font-semibold">
                <span class="inline-block w-2 h-2 rounded-full bg-[#008060] animate-ping"></span>
                <span>Livewire 4 Island Reactive</span>
            </div>
        </div>

        @island(name: 'economic-metrics', lazy: true)
            @placeholder
                <!-- SKELETON PLACEHOLDER TAILADMIN -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 animate-pulse">
                    @for($i = 0; $i < 4; $i++)
                        <div class="p-6 rounded-3xl bg-slate-200 dark:bg-slate-800/80 border border-slate-300/40 dark:border-slate-700 space-y-3">
                            <div class="h-3 w-28 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                            <div class="h-8 w-36 bg-slate-400 dark:bg-slate-600 rounded-lg"></div>
                            <div class="h-2.5 w-44 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                        </div>
                    @endfor
                </div>
            @endplaceholder

            @php
                $economicMetrics = app(EconomicMetricsService::class)->allMetrics([
                    'kecamatan_id' => $kecamatan_id,
                    'desa_id' => $desa_id,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                ]);
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

                <!-- 1. Ketepatan Sasaran (%) -->
                <div class="p-6 rounded-3xl bg-gradient-to-br from-emerald-500/10 via-white to-white dark:from-emerald-950/40 dark:via-slate-800 dark:to-slate-800 border border-emerald-500/20 dark:border-emerald-500/30 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300 uppercase tracking-wider">
                            1. Ketepatan Sasaran
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">
                            Target &ge; 80%
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-black text-emerald-600 dark:text-emerald-400">
                                {{ number_format($economicMetrics['ketepatan_sasaran'], 1) }}%
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 font-medium">
                            PUS Prioritas (Skor &ge; 7) yang Terjangkau
                        </p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            Formula: (PUS Skor &ge; 7 Dijangkau &divide; Seluruh PUS Terjangkau) &times; 100%
                        </p>
                    </div>

                    <!-- Visual progress indicator -->
                    <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-[#008060] h-full rounded-full transition-all duration-500"
                             style="width: {{ min(100, $economicMetrics['ketepatan_sasaran']) }}%"></div>
                    </div>
                </div>

                <!-- 2. Biaya per PUS Prioritas (Rp) -->
                <div class="p-6 rounded-3xl bg-gradient-to-br from-blue-500/10 via-white to-white dark:from-blue-950/40 dark:via-slate-800 dark:to-slate-800 border border-blue-500/20 dark:border-blue-500/30 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-blue-800 dark:text-blue-300 uppercase tracking-wider">
                            2. Biaya per PUS Prioritas
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">
                            Efisiensi APBD
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-1">
                            <span class="text-2xl sm:text-3xl font-black text-blue-700 dark:text-blue-400">
                                Rp {{ number_format($economicMetrics['biaya_per_pus_prioritas'], 0, ',', '.') }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 font-medium">
                            Rata-rata Biaya Penjangkauan Prioritas
                        </p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            Total Biaya Operasional &divide; Jml PUS Prioritas Terjangkau
                        </p>
                    </div>

                    <div class="mt-3 text-[11px] text-blue-600 dark:text-blue-400 flex items-center gap-1 font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        <span>Optimalisasi Alokasi BOKB DP2KBP3A</span>
                    </div>
                </div>

                <!-- 3. Efisiensi Waktu (Menit) -->
                <div class="p-6 rounded-3xl bg-gradient-to-br from-amber-500/10 via-white to-white dark:from-amber-950/40 dark:via-slate-800 dark:to-slate-800 border border-amber-500/20 dark:border-amber-500/30 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-800 dark:text-amber-300 uppercase tracking-wider">
                            3. Efisiensi Waktu
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">
                            Durasi Kunjungan
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-black text-amber-600 dark:text-amber-400">
                                {{ number_format($economicMetrics['efisiensi_waktu'], 0) }}
                            </span>
                            <span class="text-sm font-bold text-slate-500">Menit / Kasus</span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 font-medium">
                            Rata-rata Waktu Konseling &amp; Identifikasi
                        </p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            Terarah pada akar hambatan kontrasepsi
                        </p>
                    </div>

                    <div class="mt-3 text-[11px] text-amber-600 dark:text-amber-400 flex items-center gap-1 font-semibold">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Skrining 5 Indikator Mempersingkat Asesmen</span>
                    </div>
                </div>

                <!-- 4. Konversi KB Modern / Kontribusi MCPR (%) -->
                <div class="p-6 rounded-3xl bg-gradient-to-br from-purple-500/10 via-white to-white dark:from-purple-950/40 dark:via-slate-800 dark:to-slate-800 border border-purple-500/20 dark:border-purple-500/30 shadow-sm relative overflow-hidden">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-purple-800 dark:text-purple-300 uppercase tracking-wider">
                            4. Konversi KB Modern
                        </span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-200">
                            Capaian MCPR
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-black text-purple-600 dark:text-purple-400">
                                {{ number_format($economicMetrics['konversi_kb_modern'], 1) }}%
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 dark:text-slate-300 mt-2 font-medium">
                            Akseptor Beralih ke KB Modern
                        </p>
                        <p class="text-[10px] text-slate-400 mt-0.5">
                            (PUS KB Modern &divide; Total PUS Terjangkau) &times; 100%
                        </p>
                    </div>

                    <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full mt-3 overflow-hidden">
                        <div class="bg-purple-600 h-full rounded-full transition-all duration-500"
                             style="width: {{ min(100, $economicMetrics['konversi_kb_modern']) }}%"></div>
                    </div>
                </div>

            </div>
        @endisland

    </div>

    <!-- ════════════════════════════════════════════════════════════
         SECTION 3: GRAFIK APEXCHARTS MCPR & TFR
         Fitur Livewire 4 @island(lazy: true), @placeholder & wire:ignore
         ════════════════════════════════════════════════════════════ -->
    <div class="space-y-4">
        
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                    Visualisasi Capaian MCPR &amp; TFR Kabupaten Bangka
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Monitoring indikator makro program Bangga Kencana per Kecamatan
                </p>
            </div>
            <span class="text-xs text-slate-400">
                Sumber: DP2KBP3A Kab. Bangka
            </span>
        </div>

        @island(name: 'mcpr-tfr-charts', lazy: true)
            @placeholder
                <!-- SKELETON PLACEHOLDER FOR CHARTS -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-pulse">
                    <div class="h-96 rounded-3xl bg-slate-200 dark:bg-slate-800 border border-slate-300/40 dark:border-slate-700 p-6 flex flex-col justify-between">
                        <div class="h-4 w-48 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                        <div class="h-64 w-full bg-slate-300/60 dark:bg-slate-700/60 rounded-xl"></div>
                        <div class="h-3 w-36 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                    </div>
                    <div class="h-96 rounded-3xl bg-slate-200 dark:bg-slate-800 border border-slate-300/40 dark:border-slate-700 p-6 flex flex-col justify-between">
                        <div class="h-4 w-48 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                        <div class="h-64 w-full bg-slate-300/60 dark:bg-slate-700/60 rounded-xl"></div>
                        <div class="h-3 w-36 bg-slate-300 dark:bg-slate-700 rounded-md"></div>
                    </div>
                </div>
            @endplaceholder

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                <!-- ─────────────────────────────────────────────────────────────
                     GRAFIK 1: MCPR (Modern Contraceptive Prevalence Rate)
                     Bungkus elemen grafik ApexCharts TailAdmin dengan wire:ignore
                     ───────────────────────────────────────────────────────────── -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700" wire:ignore>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                Capaian MCPR per Kecamatan vs Target Renstra
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Target Renstra: 68.5% &bull; Pemantauan Akseptor Modern
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#008060]/10 text-[#008060] dark:bg-emerald-950/60 dark:text-emerald-300">
                            MCPR
                        </span>
                    </div>

                    <!-- ApexChart Container -->
                    <div id="chart-mcpr-container" class="min-h-[320px]"></div>

                    <script>
                        (function() {
                            function initMcprChart() {
                                if (typeof ApexCharts === 'undefined') {
                                    setTimeout(initMcprChart, 150);
                                    return;
                                }
                                var el = document.querySelector("#chart-mcpr-container");
                                if (!el || el.dataset.rendered === "true") return;
                                el.dataset.rendered = "true";

                                var isDark = document.documentElement.classList.contains('dark');

                                var options = {
                                    series: [
                                        {
                                            name: 'Target Renstra Nasional (68.5%)',
                                            data: [68.5, 68.5, 68.5, 68.5, 68.5, 68.5, 68.5, 68.5]
                                        },
                                        {
                                            name: 'Realisasi MCPR Bangka (%)',
                                            data: [72.4, 69.8, 67.2, 65.4, 70.8, 64.6, 68.1, 63.9]
                                        }
                                    ],
                                    chart: {
                                        type: 'bar',
                                        height: 320,
                                        toolbar: { show: false },
                                        fontFamily: 'Instrument Sans, sans-serif',
                                        background: 'transparent'
                                    },
                                    theme: {
                                        mode: isDark ? 'dark' : 'light'
                                    },
                                    colors: ['#94a3b8', '#008060'],
                                    plotOptions: {
                                        bar: {
                                            horizontal: false,
                                            columnWidth: '55%',
                                            borderRadius: 6
                                        }
                                    },
                                    dataLabels: { enabled: false },
                                    stroke: {
                                        show: true,
                                        width: 2,
                                        colors: ['transparent']
                                    },
                                    xaxis: {
                                        categories: ['Sungailiat', 'Belinyu', 'Merawang', 'Mendo Barat', 'Pemali', 'Bakam', 'Riau Silip', 'Puding Besar'],
                                        labels: {
                                            style: { fontSize: '11px', colors: '#64748b' },
                                            rotate: -30
                                        }
                                    },
                                    yaxis: {
                                        title: { text: 'Persentase (%)', style: { fontSize: '11px', color: '#64748b' } },
                                        min: 50,
                                        max: 80
                                    },
                                    fill: { opacity: 1 },
                                    tooltip: {
                                        y: {
                                            formatter: function (val) {
                                                return val + " %";
                                            }
                                        }
                                    },
                                    legend: {
                                        position: 'top',
                                        horizontalAlign: 'right',
                                        fontSize: '11px'
                                    },
                                    grid: {
                                        borderColor: isDark ? '#334155' : '#f1f5f9'
                                    }
                                };

                                var chart = new ApexCharts(el, options);
                                chart.render();
                            }
                            initMcprChart();
                        })();
                    </script>
                </div>

                <!-- ─────────────────────────────────────────────────────────────
                     GRAFIK 2: TFR (Total Fertility Rate) Proyeksi & Evaluasi
                     Bungkus elemen grafik ApexCharts TailAdmin dengan wire:ignore
                     ───────────────────────────────────────────────────────────── -->
                <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700" wire:ignore>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                                Tren Penurunan Total Fertility Rate (TFR)
                            </h4>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Target Replacement Level: 2.10 anak per wanita
                            </p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-[#0B2E59]/10 text-[#0B2E59] dark:bg-blue-950/60 dark:text-blue-300">
                            TFR
                        </span>
                    </div>

                    <!-- ApexChart Container -->
                    <div id="chart-tfr-container" class="min-h-[320px]"></div>

                    <script>
                        (function() {
                            function initTfrChart() {
                                if (typeof ApexCharts === 'undefined') {
                                    setTimeout(initTfrChart, 150);
                                    return;
                                }
                                var el = document.querySelector("#chart-tfr-container");
                                if (!el || el.dataset.rendered === "true") return;
                                el.dataset.rendered = "true";

                                var isDark = document.documentElement.classList.contains('dark');

                                var options = {
                                    series: [
                                        {
                                            name: 'Target TFR Nasional',
                                            data: [2.10, 2.10, 2.10, 2.10, 2.10, 2.10]
                                        },
                                        {
                                            name: 'Estimasi TFR Kab. Bangka',
                                            data: [2.32, 2.26, 2.21, 2.18, 2.14, 2.11]
                                        }
                                    ],
                                    chart: {
                                        type: 'line',
                                        height: 320,
                                        toolbar: { show: false },
                                        fontFamily: 'Instrument Sans, sans-serif',
                                        background: 'transparent'
                                    },
                                    theme: {
                                        mode: isDark ? 'dark' : 'light'
                                    },
                                    colors: ['#ef4444', '#0B2E59'],
                                    stroke: {
                                        width: [2, 3],
                                        curve: 'smooth',
                                        dashArray: [5, 0]
                                    },
                                    dataLabels: { enabled: false },
                                    xaxis: {
                                        categories: ['2021', '2022', '2023', '2024', '2025', '2026 (Est)'],
                                        labels: {
                                            style: { fontSize: '11px', colors: '#64748b' }
                                        }
                                    },
                                    yaxis: {
                                        title: { text: 'TFR (Rata-rata Anak)', style: { fontSize: '11px', color: '#64748b' } },
                                        min: 1.9,
                                        max: 2.5
                                    },
                                    legend: {
                                        position: 'top',
                                        horizontalAlign: 'right',
                                        fontSize: '11px'
                                    },
                                    grid: {
                                        borderColor: isDark ? '#334155' : '#f1f5f9'
                                    }
                                };

                                var chart = new ApexCharts(el, options);
                                chart.render();
                            }
                            initTfrChart();
                        })();
                    </script>
                </div>

            </div>
        @endisland

    </div>

    <!-- ════════════════════════════════════════════════════════════
         SECTION 4: REKAPITULASI KECAMATAN & REALISASI TERAKHIR
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Tabel Sebaran PUS Prioritas per Kecamatan (2 Kolom) -->
        <div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        Sebaran PUS Prioritas di 8 Kecamatan
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Monitoring beban kerja penjangkauan petugas lapangan
                    </p>
                </div>
                <a href="/screening/priority-queue" wire:navigate
                   class="text-xs font-semibold text-[#008060] dark:text-emerald-400 hover:underline">
                    Buka Antrean &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 uppercase tracking-wider text-[10px]">
                            <th class="py-3 px-2 font-bold">Kecamatan</th>
                            <th class="py-3 px-2 font-bold text-center">PUS Terdaftar</th>
                            <th class="py-3 px-2 font-bold text-center text-red-600 dark:text-red-400">Prioritas Utama (#1)</th>
                            <th class="py-3 px-2 font-bold text-center text-orange-600 dark:text-orange-400">Prioritas Tinggi (#2)</th>
                            <th class="py-3 px-2 font-bold text-right">Status Penjangkauan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($kecamatanStats as $stat)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3 px-2 font-semibold text-slate-900 dark:text-white">
                                    {{ $stat->name }}
                                </td>
                                <td class="py-3 px-2 text-center text-slate-600 dark:text-slate-300">
                                    {{ number_format($stat->pus_profiles_count) }}
                                </td>
                                <td class="py-3 px-2 text-center">
                                    @if($stat->prioritas_utama_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300">
                                            {{ $stat->prioritas_utama_count }} PUS
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-2 text-center">
                                    @if($stat->prioritas_tinggi_count > 0)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full font-bold text-[11px] bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300">
                                            {{ $stat->prioritas_tinggi_count }} PUS
                                        </span>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="py-3 px-2 text-right">
                                    <a href="/screening/priority-queue?kec={{ $stat->id }}" wire:navigate
                                       class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#008060] dark:text-emerald-400 hover:underline">
                                        <span>Pantau</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Realisasi Intervensi Terbaru (1 Kolom) -->
        <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                        Aktivitas Intervensi Terakhir
                    </h3>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>

                <div class="space-y-3.5">
                    @forelse($recentInterventions as $item)
                        <div class="p-3 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700/60">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-900 dark:text-white truncate">
                                    {{ $item->screening->pusProfile->nama_istri }}
                                </span>
                                <span class="text-[10px] text-slate-400">
                                    {{ $item->tanggal_kunjungan->format('d M') }}
                                </span>
                            </div>
                            <p class="text-[11px] text-[#008060] dark:text-emerald-400 font-medium mt-0.5">
                                {{ $item->jenis_tindakan }}
                            </p>
                            <div class="flex items-center justify-between mt-1 text-[10px] text-slate-500 dark:text-slate-400">
                                <span>Petugas: {{ $item->user?->name ?? 'PKB' }}</span>
                                <span class="font-semibold {{ $item->menggunakan_kb_modern ? 'text-emerald-600' : 'text-slate-400' }}">
                                    {{ $item->menggunakan_kb_modern ? 'KB Modern: ' . ($item->metode_kb ?? 'Ya') : 'Belum Ber-KB' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-400 text-xs">
                            <p>Belum ada catatan intervensi lapangan.</p>
                            <p class="text-[10px] text-slate-400 mt-1">Buka Antrean Prioritas untuk mencatat kunjungan.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 dark:border-slate-700 mt-4">
                <a href="/screening/priority-queue" wire:navigate
                   class="w-full flex items-center justify-center gap-2 py-2 rounded-xl text-xs font-bold text-white bg-[#008060] hover:bg-[#006e52] transition">
                    <span>Mulai Catat Intervensi Baru</span>
                </a>
            </div>
        </div>

    </div>

    <!-- Livewire 4 Modal Pencatatan Intervensi -->
    <livewire:intervention-modal />

</div>
