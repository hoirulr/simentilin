<?php

use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Realisasi Intervensi KB | SI-MENTILIN')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kb')]
    public string $filter_kb_modern = '';

    #[Url(as: 'metode')]
    public string $filter_metode = '';

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
    public ?Intervention $activeIntervention = null;

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

    public function updatedFilterKbModern(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMetode(): void
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
        $this->filter_kb_modern = '';
        $this->filter_metode = '';

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

    public function openDetailModal(int $interventionId): void
    {
        $this->activeIntervention = Intervention::with([
            'screening.pusProfile.kecamatan',
            'screening.pusProfile.desa',
            'user',
        ])->forCurrentUser(auth()->user())->findOrFail($interventionId);

        $this->authorize('view', $this->activeIntervention);
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->activeIntervention = null;
    }

    public function with(): array
    {
        $user = auth()->user();
        $isAdmin = ! $user || $user->isAdminDinas();

        $baseQuery = Intervention::query()
            ->forCurrentUser($user)
            ->with([
                'screening.pusProfile.kecamatan',
                'screening.pusProfile.desa',
                'user',
            ]);

        // Search Filter
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->whereHas('screening.pusProfile', function ($sub) use ($term) {
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
            $baseQuery->whereHas('screening.pusProfile', fn ($q) => $q->where('kecamatan_id', $this->filter_kecamatan_id));
        }

        if ($this->filter_desa_id) {
            $baseQuery->whereHas('screening.pusProfile', fn ($q) => $q->where('desa_id', $this->filter_desa_id));
        }

        // Modern Contraceptive Filter
        if ($this->filter_kb_modern === 'yes') {
            $baseQuery->where('menggunakan_kb_modern', true);
        } elseif ($this->filter_kb_modern === 'no') {
            $baseQuery->where('menggunakan_kb_modern', false);
        }

        // Method Filter
        if ($this->filter_metode !== '') {
            $baseQuery->where('metode_kb', 'like', '%' . $this->filter_metode . '%');
        }

        // Date Range Filters
        if ($this->start_date) {
            $baseQuery->where('tanggal_kunjungan', '>=', $this->start_date);
        }
        if ($this->end_date) {
            $baseQuery->where('tanggal_kunjungan', '<=', $this->end_date);
        }

        // Aggregate Statistics across filtered dataset
        $statsQuery = clone $baseQuery;
        $totalInterventions = $statsQuery->count();
        $totalBiaya = (float) $statsQuery->sum('biaya_operasional');
        $avgDurasi = $totalInterventions > 0 ? round((float) $statsQuery->avg('durasi_menit')) : 0;
        $modernKbCount = $statsQuery->where('menggunakan_kb_modern', true)->count();
        $modernKbRate = $totalInterventions > 0 ? round(($modernKbCount / $totalInterventions) * 100) : 0;

        // Paginated records
        $interventions = $baseQuery->latest('tanggal_kunjungan')->latest('id')->paginate(10);

        // Territory dropdowns
        $kecamatans = Kecamatan::orderBy('name')->get();
        $desas = $this->filter_kecamatan_id
            ? Desa::where('kecamatan_id', $this->filter_kecamatan_id)->orderBy('name')->get()
            : collect();

        return compact(
            'isAdmin',
            'interventions',
            'totalInterventions',
            'totalBiaya',
            'avgDurasi',
            'modernKbCount',
            'modernKbRate',
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
                    <span>Monitoring &amp; Evaluasi Lapangan SI-MENTILIN</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Realisasi &amp; Evaluasi Intervensi KB
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Rekam jejak kunjungan penjangkauan, durasi konseling, biaya operasional riil, serta konversi adopsi kontrasepsi modern (MCPR) dari hasil tindak lanjut skrining 5 indikator.
                </p>
            </div>

            <!-- Quick Action Links -->
            <div class="flex items-center gap-3">
                <a href="{{ route('screening.priority-queue') }}" wire:navigate
                   class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-white text-[#0B2E59] hover:bg-slate-100 font-bold text-sm shadow-xl shadow-black/20 transition transform active:scale-95 focus:outline-none ring-2 ring-white/30">
                    <svg class="w-5 h-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Antrean Prioritas PUS</span>
                </a>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         STATISTIC CARDS
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Intervensi Selesai -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kunjungan Selesai</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalInterventions) }} <span class="text-sm font-medium text-slate-400">PUS</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[#0B2E59] dark:text-blue-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>Telah dijangkau PKB / PLKB</span>
            </p>
        </div>

        <!-- Adopsi KB Modern -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Konversi KB Modern</p>
                    <h3 class="text-2xl font-black text-[#008060] dark:text-emerald-400 mt-1">{{ $modernKbRate }}% <span class="text-xs font-medium text-slate-400">({{ $modernKbCount }} PUS)</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Mendorong peningkatan capaian MCPR</span>
            </p>
        </div>

        <!-- Biaya Operasional Riil -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Biaya Riil</p>
                    <h3 class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">Rp {{ number_format($totalBiaya, 0, ',', '.') }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Rata-rata: Rp {{ $totalInterventions > 0 ? number_format($totalBiaya / $totalInterventions, 0, ',', '.') : '0' }} / PUS</span>
            </p>
        </div>

        <!-- Rata-rata Durasi Konseling -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rata-rata Durasi</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $avgDurasi }} <span class="text-sm font-medium text-slate-400">Menit</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span>Efisiensi konseling per sasaran</span>
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

            <!-- Modern KB Filter -->
            <div>
                <select wire:model.live="filter_kb_modern"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Status KB</option>
                    <option value="yes">Menggunakan KB Modern</option>
                    <option value="no">Belum Ber-KB / Menolak</option>
                </select>
            </div>

            <!-- Metode KB Filter -->
            <div>
                <select wire:model.live="filter_metode"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Metode Kontrasepsi</option>
                    <option value="Implan">Implan / Susuk (MKJP)</option>
                    <option value="IUD">IUD / Spiral (MKJP)</option>
                    <option value="MOW">MOW / Tubektomi (MKJP)</option>
                    <option value="MOP">MOP / Vasektomi (MKJP)</option>
                    <option value="Suntik">Suntik KB</option>
                    <option value="Pil">Pil KB</option>
                    <option value="Kondom">Kondom</option>
                </select>
            </div>

            <!-- Kecamatan Filter (If Admin) -->
            <div>
                <select wire:model.live="filter_kecamatan_id"
                        @if(! $isAdmin) disabled @endif
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition disabled:opacity-60">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatans as $kec)
                        <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                    @endforeach
                </select>
            </div>

        </div>

        <!-- Row 2: Date Filters & Reset Button -->
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

            <!-- Desa Filter -->
            <div>
                <select wire:model.live="filter_desa_id"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Desa / Kelurahan</option>
                    @foreach($desas as $desa)
                        <option value="{{ $desa->id }}">{{ $desa->name }}</option>
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
         INTERVENTIONS TABLE (TailAdmin Table Three)
         ════════════════════════════════════════════════════════════ -->
    <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-4 px-6">Tanggal &amp; Sasaran PUS</th>
                        <th class="py-4 px-6">Wilayah &amp; Petugas</th>
                        <th class="py-4 px-6">Hasil Konseling &amp; Metode KB</th>
                        <th class="py-4 px-6 text-center">Durasi &amp; Biaya Riil</th>
                        <th class="py-4 px-6 text-right">Rincian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs text-slate-700 dark:text-slate-300">
                    @forelse($interventions as $item)
                        @php
                            $pus = $item->screening?->pusProfile;
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition">
                            
                            <!-- Tanggal & PUS -->
                            <td class="py-4 px-6">
                                <div class="flex items-start gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#008060] dark:text-emerald-300 flex items-center justify-center font-bold text-xs shrink-0 ring-1 ring-emerald-300 dark:ring-emerald-700">
                                        {{ $item->tanggal_kunjungan->format('d') }}
                                        <span class="text-[9px] block uppercase">{{ $item->tanggal_kunjungan->format('M') }}</span>
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-900 dark:text-white text-sm leading-tight">
                                            {{ $pus ? 'Ny. ' . $pus->nama_istri : 'PUS #' . $item->screening_id }}
                                        </p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                                            Suami: {{ $pus?->nama_suami ?? '-' }} &bull; Usia: {{ $pus?->usiaIstri() ?? '-' }} th &bull; Anak: {{ $pus?->jumlah_anak_lahir_hidup ?? '-' }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Wilayah & Petugas -->
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

                            <!-- Hasil Tindakan & Metode KB -->
                            <td class="py-4 px-6">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        @if($item->menggunakan_kb_modern)
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                                <span>KB Modern</span>
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 border border-red-300 dark:border-red-700">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                                <span>Belum Ber-KB</span>
                                            </span>
                                        @endif

                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            {{ $item->metode_kb ?? '-' }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 line-clamp-1 italic">
                                        {{ $item->catatan ?: $item->jenis_tindakan }}
                                    </p>
                                </div>
                            </td>

                            <!-- Durasi & Biaya -->
                            <td class="py-4 px-6 text-center">
                                <div class="space-y-1">
                                    <span class="inline-flex items-center gap-1 font-bold text-slate-800 dark:text-slate-200">
                                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span>{{ $item->durasi_menit }} Menit</span>
                                    </span>
                                    <p class="text-[11px] font-semibold text-amber-600 dark:text-amber-400">
                                        Rp {{ number_format($item->biaya_operasional, 0, ',', '.') }}
                                    </p>
                                </div>
                            </td>

                            <!-- Action -->
                            <td class="py-4 px-6 text-right">
                                <button wire:click="openDetailModal({{ $item->id }})"
                                        type="button"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-[#008060] hover:text-white dark:bg-slate-700/60 dark:hover:bg-[#008060] text-slate-700 dark:text-slate-200 text-xs font-semibold transition">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <span>Detail</span>
                                </button>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    <p class="font-semibold text-sm">Belum ada catatan realisasi intervensi yang cocok.</p>
                                    <p class="text-xs">Catat intervensi dari menu Antrean Prioritas PUS untuk merekam log kunjungan.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($interventions->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $interventions->links() }}
            </div>
        @endif
    </div>

    <!-- ════════════════════════════════════════════════════════════
         MODAL: DETAIL CATATAN INTERVENSI LENGKAP
         ════════════════════════════════════════════════════════════ -->
    @if($showDetailModal && $activeIntervention)
        @php
            $pus = $activeIntervention->screening?->pusProfile;
            $screening = $activeIntervention->screening;
        @endphp
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-detail-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="closeDetailModal"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 672px;">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#008060] dark:text-emerald-400 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-detail-title">
                                    Lembar Bukti Realisasi Intervensi
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    ID Intervensi: #{{ $activeIntervention->id }} &bull; Tanggal: {{ $activeIntervention->tanggal_kunjungan->format('d F Y') }}
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
                        
                        <!-- PUS Identity Box -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white text-sm">
                                    Ny. {{ $pus?->nama_istri }} &amp; Tn. {{ $pus?->nama_suami }}
                                </span>
                                @if($screening?->is_mupar)
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-400">
                                        Flag MUPAR
                                    </span>
                                @endif
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] text-slate-500 dark:text-slate-400 pt-1 border-t border-slate-200/60 dark:border-slate-800">
                                <div><span class="block text-slate-400">Usia Istri:</span> <strong class="text-slate-700 dark:text-slate-200">{{ $pus?->usiaIstri() }} Tahun</strong></div>
                                <div><span class="block text-slate-400">Anak Hidup:</span> <strong class="text-slate-700 dark:text-slate-200">{{ $pus?->jumlah_anak_lahir_hidup }} Orang</strong></div>
                                <div><span class="block text-slate-400">Skor SI-MENTILIN:</span> <strong class="text-[#008060] dark:text-emerald-400">{{ $screening?->total_skor }} / 15</strong></div>
                                <div><span class="block text-slate-400">Prioritas Tindakan:</span> <strong class="text-slate-700 dark:text-slate-200">{{ $screening?->prioritas_akhir }}</strong></div>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                Alamat: {{ $pus?->alamat }}, Kec. {{ $pus?->kecamatan?->name }}, Desa {{ $pus?->desa?->name }}
                            </p>
                        </div>

                        <!-- Realization Stats Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="p-3 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-[10px] text-emerald-800 dark:text-emerald-300 font-semibold uppercase">Status Kontrasepsi</p>
                                <p class="text-sm font-black text-[#008060] dark:text-emerald-400 mt-1">
                                    {{ $activeIntervention->menggunakan_kb_modern ? 'KB Modern' : 'Belum Ber-KB' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40">
                                <p class="text-[10px] text-emerald-800 dark:text-emerald-300 font-semibold uppercase">Metode Terpilih</p>
                                <p class="text-sm font-black text-slate-900 dark:text-white mt-1 truncate">
                                    {{ $activeIntervention->metode_kb ?: '-' }}
                                </p>
                            </div>
                            <div class="p-3 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40">
                                <p class="text-[10px] text-amber-800 dark:text-amber-300 font-semibold uppercase">Durasi Konseling</p>
                                <p class="text-sm font-black text-amber-600 dark:text-amber-400 mt-1">
                                    {{ $activeIntervention->durasi_menit }} Menit
                                </p>
                            </div>
                            <div class="p-3 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/40">
                                <p class="text-[10px] text-amber-800 dark:text-amber-300 font-semibold uppercase">Biaya Operasional</p>
                                <p class="text-sm font-black text-amber-600 dark:text-amber-400 mt-1">
                                    Rp {{ number_format($activeIntervention->biaya_operasional, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>

                        <!-- Officer and Action Description -->
                        <div class="space-y-3 pt-2">
                            <div>
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Jenis Tindakan &amp; Deskripsi</span>
                                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700">
                                    <p class="font-bold text-slate-900 dark:text-white">{{ $activeIntervention->jenis_tindakan }}</p>
                                    <p class="text-slate-600 dark:text-slate-400 mt-1">{{ $activeIntervention->deskripsi_tindakan ?: 'Tidak ada deskripsi tambahan.' }}</p>
                                </div>
                            </div>

                            @if($activeIntervention->catatan)
                                <div>
                                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Catatan Hasil Kunjungan PKB</span>
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/40 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300">
                                        {{ $activeIntervention->catatan }}
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200 dark:border-slate-700">
                                <span>Petugas Pencatat: <strong>{{ $activeIntervention->user?->name }}</strong></span>
                                <span>Terekam: {{ $activeIntervention->created_at?->format('d/m/Y H:i') }} WIB</span>
                            </div>
                        </div>

                        <!-- Close Button -->
                        <div class="flex items-center justify-end pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="closeDetailModal"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow transition">
                                Tutup
                            </button>
                        </div>

                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
