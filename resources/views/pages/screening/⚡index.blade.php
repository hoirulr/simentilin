<?php

use App\Enums\PriorityCategory;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\Screening;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Rekapitulasi Skrining | SI-MENTILIN')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kategori')]
    public string $filter_kategori = '';

    #[Url(as: 'mupar')]
    public string $filter_mupar = '';

    #[Url(as: 'status_intervensi')]
    public string $filter_status_intervensi = '';

    #[Url(as: 'kec')]
    public ?int $filter_kecamatan_id = null;

    #[Url(as: 'desa')]
    public ?int $filter_desa_id = null;

    #[Url(as: 'start')]
    public ?string $start_date = null;

    #[Url(as: 'end')]
    public ?string $end_date = null;

    // Detail Modal state
    public bool $showDetailModal = false;
    public ?Screening $activeScreening = null;

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && ! $user->isAdminDinas()) {
            $this->filter_kecamatan_id = $user->kecamatan_id;
            if ($user->desa_id) {
                $this->filter_desa_id = $user->desa_id;
            }
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKategori(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMupar(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatusIntervensi(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKecamatanId(): void
    {
        $this->filter_desa_id = null;
        $this->resetPage();
    }

    public function updatedFilterDesaId(): void
    {
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter_kategori = '';
        $this->filter_mupar = '';
        $this->filter_status_intervensi = '';

        $user = auth()->user();
        if (! $user || $user->isAdminDinas()) {
            $this->filter_kecamatan_id = null;
            $this->filter_desa_id = null;
        } else {
            $this->filter_desa_id = null;
        }

        $this->start_date = null;
        $this->end_date = null;
        $this->resetPage();
    }

    public function openDetailModal(int $screeningId): void
    {
        $this->activeScreening = Screening::with([
            'pusProfile.kecamatan',
            'pusProfile.desa',
            'user',
            'interventions.user',
        ])->forCurrentUser(auth()->user())->findOrFail($screeningId);

        $this->authorize('view', $this->activeScreening);
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->activeScreening = null;
    }

    public function with(): array
    {
        $user = auth()->user();
        $isAdmin = ! $user || $user->isAdminDinas();

        $baseQuery = Screening::query()
            ->forCurrentUser($user)
            ->with([
                'pusProfile.kecamatan',
                'pusProfile.desa',
                'user',
                'interventions',
            ])
            ->withCount('interventions');

        // Search Filter
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->whereHas('pusProfile', function ($sub) use ($term) {
                    $sub->where('nama_istri', 'like', $term)
                        ->orWhere('nama_suami', 'like', $term)
                        ->orWhere('alamat', 'like', $term);
                })->orWhereHas('user', function ($sub) use ($term) {
                    $sub->where('name', 'like', $term);
                })->orWhere('catatan', 'like', $term);
            });
        }

        // Territory Filters
        if ($this->filter_kecamatan_id) {
            $baseQuery->whereHas('pusProfile', fn ($q) => $q->where('kecamatan_id', $this->filter_kecamatan_id));
        }

        if ($this->filter_desa_id) {
            $baseQuery->whereHas('pusProfile', fn ($q) => $q->where('desa_id', $this->filter_desa_id));
        }

        // Kategori Filter
        if ($this->filter_kategori !== '') {
            $baseQuery->where('kategori', $this->filter_kategori);
        }

        // MUPAR Filter
        if ($this->filter_mupar === 'yes') {
            $baseQuery->where('is_mupar', true);
        } elseif ($this->filter_mupar === 'no') {
            $baseQuery->where('is_mupar', false);
        }

        // Intervention Status Filter
        if ($this->filter_status_intervensi === 'sudah') {
            $baseQuery->has('interventions');
        } elseif ($this->filter_status_intervensi === 'belum') {
            $baseQuery->doesntHave('interventions');
        }

        // Date Range Filters
        if ($this->start_date) {
            $baseQuery->where('tanggal_skrining', '>=', $this->start_date);
        }
        if ($this->end_date) {
            $baseQuery->where('tanggal_skrining', '<=', $this->end_date);
        }

        // Aggregate Statistics across filtered query
        $statsQuery = clone $baseQuery;
        $totalScreenings = $statsQuery->count();
        $totalHighPriority = (clone $statsQuery)->where('kategori', PriorityCategory::TINGGI)->count();
        $totalMupar = (clone $statsQuery)->where('is_mupar', true)->count();
        $totalIntervened = (clone $statsQuery)->has('interventions')->count();
        $interventionRate = $totalScreenings > 0 ? round(($totalIntervened / $totalScreenings) * 100) : 0;

        // Paginated records
        $screenings = $baseQuery->latest('tanggal_skrining')->latest('id')->paginate(10);

        // Territory dropdowns
        $kecamatans = Kecamatan::orderBy('name')->get();
        $desas = $this->filter_kecamatan_id
            ? Desa::where('kecamatan_id', $this->filter_kecamatan_id)->orderBy('name')->get()
            : collect();

        return compact(
            'isAdmin',
            'screenings',
            'totalScreenings',
            'totalHighPriority',
            'totalMupar',
            'totalIntervened',
            'interventionRate',
            'kecamatans',
            'desas'
        );
    }
};
?>

<div class="space-y-6">

    <!-- ════════════════════════════════════════════════════════════
         TOP BANNER / HEADER
         ════════════════════════════════════════════════════════════ -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0B2E59] via-[#0e3a6e] to-[#008060] p-6 sm:p-8 text-white shadow-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-emerald-300 text-xs font-semibold border border-white/15">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Database Audit Skrining Lima Indikator</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Rekapitulasi Data Skrining PUS
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Master riwayat lengkap wawancara skrining 5 indikator (P1–P5), hasil skoring (0–15), status penanda strategis MUPAR, serta pemantauan status tindak lanjut intervensi di lapangan.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('screening.create') }}" wire:navigate
                   class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-[#008060] to-[#10B981] hover:from-[#006e52] hover:to-[#059669] text-white font-bold text-sm shadow-xl shadow-black/20 transition transform active:scale-95 focus:outline-none ring-2 ring-white/30">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Skrining PUS Baru</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         STATISTIC CARDS
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Skrining -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Skrining</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalScreenings) }} <span class="text-sm font-medium text-slate-400">Data</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[#0B2E59] dark:text-blue-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>Terekam dalam sistem</span>
            </p>
        </div>

        <!-- Prioritas Tinggi -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Skor Tinggi (11–15)</p>
                    <h3 class="text-2xl font-black text-red-600 dark:text-red-400 mt-1">{{ number_format($totalHighPriority) }} <span class="text-sm font-medium text-slate-400">PUS</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                <span>Sasaran butuh penjangkauan segera</span>
            </p>
        </div>

        <!-- MUPAR Flag -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kasus MUPAR</p>
                    <h3 class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">{{ number_format($totalMupar) }} <span class="text-sm font-medium text-slate-400">PUS</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span>Muda Paritas Rendah (≤ 35 th, ≤ 2 anak)</span>
            </p>
        </div>

        <!-- Sudah Diintervensi -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sudah Diintervensi</p>
                    <h3 class="text-2xl font-black text-[#008060] dark:text-emerald-400 mt-1">{{ $interventionRate }}% <span class="text-xs font-medium text-slate-400">({{ $totalIntervened }}/{{ $totalScreenings }})</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Realisasi kunjungan &amp; konseling</span>
            </p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         FILTER & SEARCH BAR
         ════════════════════════════════════════════════════════════ -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            
            <!-- Search Input -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Cari PUS atau petugas..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
            </div>

            <!-- Kategori Skor Filter -->
            <div>
                <select wire:model.live="filter_kategori"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Kategori Skor</option>
                    <option value="tinggi">Tinggi (Skor 11–15)</option>
                    <option value="sedang">Sedang (Skor 7–10)</option>
                    <option value="rendah">Rendah (Skor 0–6)</option>
                </select>
            </div>

            <!-- MUPAR Filter -->
            <div>
                <select wire:model.live="filter_mupar"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Status MUPAR</option>
                    <option value="yes">Hanya MUPAR (≤ 35 th, ≤ 2 anak)</option>
                    <option value="no">Non-MUPAR</option>
                </select>
            </div>

            <!-- Status Intervensi Filter -->
            <div>
                <select wire:model.live="filter_status_intervensi"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Status Intervensi</option>
                    <option value="sudah">Sudah Diintervensi</option>
                    <option value="belum">Belum Ditindaklanjuti</option>
                </select>
            </div>

        </div>

        <!-- Row 2: Date Filters & Territory -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-center">
            <!-- Start Date -->
            <div class="flex items-center gap-2">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold whitespace-nowrap">Dari:</span>
                <input wire:model.live="start_date"
                       type="date"
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
            </div>

            <!-- End Date -->
            <div class="flex items-center gap-2">
                <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold whitespace-nowrap">Sampai:</span>
                <input wire:model.live="end_date"
                       type="date"
                       class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
            </div>

            <!-- Kecamatan Filter -->
            <div>
                <select wire:model.live="filter_kecamatan_id"
                        @if(! $isAdmin) disabled @endif
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition disabled:opacity-60">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatans as $kec)
                        <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Reset Button -->
            <div>
                <button wire:click="resetFilters"
                        type="button"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span>Reset Filter</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         SCREENINGS TABLE (TailAdmin Table Three)
         ════════════════════════════════════════════════════════════ -->
    <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-4 px-6">Tanggal &amp; Sasaran PUS</th>
                        <th class="py-4 px-6 text-center">Skor 5 Indikator (P1–P5)</th>
                        <th class="py-4 px-6 text-center">Total Skor &amp; Prioritas</th>
                        <th class="py-4 px-6">Wilayah &amp; Pewawancara</th>
                        <th class="py-4 px-6 text-center">Status Intervensi</th>
                        <th class="py-4 px-6 text-right">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs text-slate-700 dark:text-slate-300">
                    @forelse($screenings as $item)
                        @php
                            $pus = $item->pusProfile;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition">
                            
                            <!-- Tanggal & PUS -->
                            <td class="py-4 px-6">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#0B2E59] dark:text-blue-300 flex items-center justify-center font-bold text-xs shrink-0 ring-1 ring-blue-300 dark:ring-blue-700">
                                        {{ $item->tanggal_skrining->format('d') }}
                                        <span class="text-[9px] block uppercase">{{ $item->tanggal_skrining->format('M') }}</span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white text-sm leading-tight">
                                            {{ $pus ? 'Ny. ' . $pus->nama_istri : 'PUS #' . $item->pus_profile_id }}
                                        </p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Suami: {{ $pus?->nama_suami ?? '-' }} &bull; Usia: {{ $pus?->usiaIstri() ?? '-' }} th &bull; Anak: {{ $pus?->jumlah_anak_lahir_hidup ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Skor 5 Indikator -->
                            <td class="py-4 px-6 text-center">
                                <div class="inline-flex items-center gap-1 font-mono text-[11px] bg-slate-100 dark:bg-slate-700/50 px-2 py-1 rounded-lg">
                                    <span title="P1: Usia Istri" class="px-1 font-bold text-slate-800 dark:text-slate-200">P1:{{ $item->p1_usia_istri }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span title="P2: Jumlah Anak" class="px-1 font-bold text-slate-800 dark:text-slate-200">P2:{{ $item->p2_jumlah_anak }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span title="P3: Status Kontrasepsi" class="px-1 font-bold text-slate-800 dark:text-slate-200">P3:{{ $item->p3_status_kontrasepsi }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span title="P4: Tujuan Reproduksi" class="px-1 font-bold text-slate-800 dark:text-slate-200">P4:{{ $item->p4_tujuan_reproduksi }}</span>
                                    <span class="text-slate-300">|</span>
                                    <span title="P5: Risiko Kehamilan" class="px-1 font-bold text-slate-800 dark:text-slate-200">P5:{{ $item->p5_risiko_kehamilan }}</span>
                                </div>
                            </td>

                            <!-- Total Skor & Prioritas -->
                            <td class="py-4 px-6 text-center">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <span class="px-2 py-0.5 rounded-md font-mono font-black text-xs {{ $item->total_skor >= 11 ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' : ($item->total_skor >= 7 ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300') }}">
                                            Skor {{ $item->total_skor }}/15
                                        </span>
                                        @if($item->is_mupar)
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-400">
                                                MUPAR
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">
                                        {{ $item->prioritas_akhir }}
                                    </p>
                                </div>
                            </td>

                            <!-- Wilayah & Pewawancara -->
                            <td class="py-4 px-6">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                        <span>Kec. {{ $pus?->kecamatan?->name ?? '-' }}</span>
                                    </p>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 ml-4.5">
                                        Desa {{ $pus?->desa?->name ?? '-' }}
                                    </p>
                                    <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 flex items-center gap-1">
                                        <span class="text-slate-400">Petugas:</span>
                                        <span class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[150px]">{{ $item->user?->name ?? 'PKB' }}</span>
                                    </p>
                                </div>
                            </td>

                            <!-- Status Intervensi -->
                            <td class="py-4 px-6 text-center">
                                @if($item->interventions_count > 0)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/60 text-[#008060] dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span>Sudah ({{ $item->interventions_count }}x)</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                        <span>Belum Tindak Lanjut</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Action -->
                            <td class="py-4 px-6 text-right">
                                <button wire:click="openDetailModal({{ $item->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-[#008060] hover:text-white dark:bg-slate-700/60 dark:hover:bg-[#008060] text-slate-700 dark:text-slate-200 text-xs font-semibold transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <span>Lembar Skrining</span>
                                </button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <p class="font-semibold text-sm">Tidak ada catatan skrining yang cocok dengan filter.</p>
                                    <p class="text-xs">Ubah parameter pencarian atau gunakan tombol reset filter.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($screenings->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $screenings->links() }}
            </div>
        @endif
    </div>

    <!-- ════════════════════════════════════════════════════════════
         MODAL: DETAIL LEMBAR SKRINING LENGKAP
         ════════════════════════════════════════════════════════════ -->
    @if($showDetailModal && $activeScreening)
        @php
            $pus = $activeScreening->pusProfile;
        @endphp
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-screening-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="closeDetailModal"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 672px;">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#0B2E59] dark:text-blue-400 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-screening-title">
                                    Lembar Audit Skrining SI-MENTILIN
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    No. Skrining: #{{ $activeScreening->id }} &bull; Tanggal: {{ $activeScreening->tanggal_skrining->format('d F Y') }}
                                </p>
                            </div>
                        </div>

                        <button wire:click="closeDetailModal"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-4 mt-5 text-xs text-slate-700 dark:text-slate-300">
                        
                        <!-- PUS Info Banner -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-slate-900 dark:text-white text-base">
                                    Ny. {{ $pus?->nama_istri }} &amp; Tn. {{ $pus?->nama_suami }}
                                </h4>
                                <p class="text-slate-500 dark:text-slate-400 mt-0.5">
                                    Usia: {{ $pus?->usiaIstri() }} Tahun &bull; Anak Hidup: {{ $pus?->jumlah_anak_lahir_hidup }} Orang &bull; Alamat: {{ $pus?->alamat }}, Kec. {{ $pus?->kecamatan?->name }}
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 rounded-xl font-bold font-mono text-sm {{ $activeScreening->total_skor >= 11 ? 'bg-red-100 text-red-800 border border-red-300' : ($activeScreening->total_skor >= 7 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300') }}">
                                    Skor: {{ $activeScreening->total_skor }} / 15
                                </span>
                                @if($activeScreening->is_mupar)
                                    <span class="px-2.5 py-1 rounded-xl text-xs font-bold bg-purple-100 text-purple-800 border border-purple-400">
                                        MUPAR
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- 5 Indicators Breakdown Table -->
                        <div class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="bg-slate-50 dark:bg-slate-900/50 text-[11px] font-bold text-slate-500 uppercase border-b border-slate-200 dark:border-slate-700">
                                        <th class="py-2.5 px-4">Parameter Skoring</th>
                                        <th class="py-2.5 px-4 text-center">Bobot Nilai</th>
                                        <th class="py-2.5 px-4">Status &amp; Keterangan Respon</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                    <tr>
                                        <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">P1: Usia Istri</td>
                                        <td class="py-2.5 px-4 text-center font-bold text-[#008060]">{{ $activeScreening->p1_usia_istri }}</td>
                                        <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $activeScreening->p1_usia_istri === 3 ? '≤ 35 tahun (Risiko reproduksi tinggi)' : ($activeScreening->p1_usia_istri === 2 ? '36–39 tahun' : ($activeScreening->p1_usia_istri === 1 ? '40–44 tahun' : '≥ 45 tahun')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">P2: Jumlah Anak Lahir Hidup</td>
                                        <td class="py-2.5 px-4 text-center font-bold text-[#008060]">{{ $activeScreening->p2_jumlah_anak }}</td>
                                        <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $activeScreening->p2_jumlah_anak === 3 ? '0–2 anak (Paritas rendah)' : ($activeScreening->p2_jumlah_anak === 2 ? '3 anak' : ($activeScreening->p2_jumlah_anak === 1 ? '4 anak' : '≥ 5 anak')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">P3: Status Kontrasepsi</td>
                                        <td class="py-2.5 px-4 text-center font-bold text-[#008060]">{{ $activeScreening->p3_status_kontrasepsi }}</td>
                                        <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $activeScreening->p3_status_kontrasepsi === 3 ? 'Tidak menggunakan padahal tidak ingin hamil' : ($activeScreening->p3_status_kontrasepsi === 2 ? 'Tidak konsisten / kurang sesuai' : ($activeScreening->p3_status_kontrasepsi === 1 ? 'Ada pertanyaan / kekhawatiran ringan' : 'Konsisten & sesuai tujuan')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">P4: Tujuan Reproduksi</td>
                                        <td class="py-2.5 px-4 text-center font-bold text-[#008060]">{{ $activeScreening->p4_tujuan_reproduksi }}</td>
                                        <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $activeScreening->p4_tujuan_reproduksi === 3 ? 'Ingin menunda jangka panjang / stop anak tapi belum ber-KB sesuai' : ($activeScreening->p4_tujuan_reproduksi === 2 ? 'Ingin menunda kehamilan' : ($activeScreening->p4_tujuan_reproduksi === 1 ? 'Belum merencanakan tapi masih terbuka' : 'Merencanakan kehamilan')) }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">P5: Risiko Kehamilan Tidak Direncanakan</td>
                                        <td class="py-2.5 px-4 text-center font-bold text-[#008060]">{{ $activeScreening->p5_risiko_kehamilan }}</td>
                                        <td class="py-2.5 px-4 text-slate-600 dark:text-slate-400">
                                            {{ $activeScreening->p5_risiko_kehamilan === 3 ? 'Aktif seksual, tidak ber-KB & tidak menginginkan kehamilan' : ($activeScreening->p5_risiko_kehamilan === 2 ? 'Penggunaan kontrasepsi tidak konsisten' : ($activeScreening->p5_risiko_kehamilan === 1 ? 'Ada kendala / efek samping ringan' : 'Kontrasepsi konsisten & tepat')) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Prioritas & Tindakan Ketetapan -->
                        <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40 space-y-1">
                            <p class="text-[11px] font-bold text-amber-900 dark:text-amber-300 uppercase tracking-wider">
                                Ketetapan Tindakan Petugas Lapangan (Urutan Antrean #{{ $activeScreening->urutan_antrean }})
                            </p>
                            <p class="text-xs font-bold text-slate-900 dark:text-white">
                                {{ $activeScreening->prioritas_akhir }}
                            </p>
                            @if($activeScreening->catatan)
                                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic">
                                    Catatan Wawancara: {{ $activeScreening->catatan }}
                                </p>
                            @endif
                        </div>

                        <!-- Interventions History if any -->
                        <div>
                            <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-2">Riwayat Tindak Lanjut Intervensi</span>
                            @if($activeScreening->interventions->isNotEmpty())
                                <div class="space-y-2">
                                    @foreach($activeScreening->interventions as $inv)
                                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                            <div>
                                                <p class="font-bold text-slate-900 dark:text-white">{{ $inv->jenis_tindakan }} &bull; {{ $inv->tanggal_kunjungan->format('d/m/Y') }}</p>
                                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                                    Petugas: {{ $inv->user?->name }} &bull; Metode: <strong>{{ $inv->metode_kb ?: 'Belum Ber-KB' }}</strong> &bull; Biaya: Rp {{ number_format($inv->biaya_operasional, 0, ',', '.') }}
                                                </p>
                                            </div>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $inv->menggunakan_kb_modern ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $inv->menggunakan_kb_modern ? 'KB Modern' : 'Belum Ber-KB' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-300 dark:border-slate-700 text-center text-slate-500">
                                    Belum ada catatan intervensi kunjungan untuk skrining ini.
                                </div>
                            @endif
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400">
                            <span>Pewawancara: <strong>{{ $activeScreening->user?->name }}</strong></span>
                            <button wire:click="closeDetailModal"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow transition">
                                Tutup Lembar Skrining
                            </button>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
