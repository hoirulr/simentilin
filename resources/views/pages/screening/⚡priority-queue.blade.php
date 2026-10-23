<?php

use App\Enums\PriorityCategory;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\Screening;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'urutan')]
    public string $filter_urutan = '';

    #[Url(as: 'mupar')]
    public string $filter_mupar = '';

    #[Url(as: 'kec')]
    public ?int $kecamatan_id = null;

    #[Url(as: 'desa')]
    public ?int $desa_id = null;

    // Detail Modal state
    public bool $showDetailModal = false;
    public ?Screening $activeScreening = null;

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
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterUrutan(): void
    {
        $this->resetPage();
    }

    public function updatedFilterMupar(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter_urutan = '';
        $this->filter_mupar = '';

        $user = auth()->user();
        if (! $user || $user->isAdminDinas()) {
            $this->kecamatan_id = null;
            $this->desa_id = null;
        } else {
            $this->desa_id = null;
        }

        $this->resetPage();
    }

    public function openDetailModal(int $screeningId): void
    {
        $this->activeScreening = Screening::with(['pusProfile.kecamatan', 'pusProfile.desa', 'user'])->findOrFail($screeningId);
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

        // Base query with territory scope
        $query = Screening::query()
            ->forCurrentUser($user)
            ->with(['pusProfile.kecamatan', 'pusProfile.desa', 'user']);

        // Search by wife, husband, or address
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->whereHas('pusProfile', function ($q) use ($term) {
                $q->where('nama_istri', 'like', $term)
                  ->orWhere('nama_suami', 'like', $term)
                  ->orWhere('alamat', 'like', $term)
                  ->orWhere('rt_rw', 'like', $term);
            });
        }

        // Territory filter
        if ($this->kecamatan_id) {
            $query->whereHas('pusProfile', fn ($q) => $q->where('kecamatan_id', $this->kecamatan_id));
        }
        if ($this->desa_id) {
            $query->whereHas('pusProfile', fn ($q) => $q->where('desa_id', $this->desa_id));
        }

        // Priority Rank filter
        if ($this->filter_urutan !== '') {
            $query->where('urutan_antrean', (int) $this->filter_urutan);
        }

        // MUPAR filter
        if ($this->filter_mupar === '1') {
            $query->where('is_mupar', true);
        } elseif ($this->filter_mupar === '0') {
            $query->where('is_mupar', false);
        }

        // Core Requirement: ORDER BY urutan_antrean ASC (#1 Prioritas Utama first),
        // then total_skor DESC, then latest tanggal_skrining.
        $query->orderBy('urutan_antrean', 'asc')
              ->orderBy('total_skor', 'desc')
              ->latest('tanggal_skrining');

        // Paginate queue
        $queue = $query->paginate(10);

        // Calculate summary counters for the top cards
        $statsBase = Screening::query()->forCurrentUser($user);
        $totalAntrean = (clone $statsBase)->count();
        $countPrioritasUtama = (clone $statsBase)->where('urutan_antrean', 1)->count();
        $countPrioritasTinggi = (clone $statsBase)->where('urutan_antrean', 2)->count();
        $countEdukasiTerjadwal = (clone $statsBase)->where('urutan_antrean', 3)->count();

        // Territory lists for filter dropdowns
        $kecamatans = Kecamatan::orderBy('name')->get();
        $desas = $this->kecamatan_id
            ? Desa::where('kecamatan_id', $this->kecamatan_id)->orderBy('name')->get()
            : collect();

        return compact(
            'queue',
            'totalAntrean',
            'countPrioritasUtama',
            'countPrioritasTinggi',
            'countEdukasiTerjadwal',
            'kecamatans',
            'desas'
        );
    }
};
?>

<div>
    <!-- Page Header & Title -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-500/10 text-red-600 dark:bg-red-500/20 dark:text-red-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    Antrean Prioritas Kunjungan PKB / PLKB
                </h1>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Daftar urutan sasaran hasil skrining lima indikator, diprioritaskan dari <strong>#1 Prioritas Utama (Skor Tinggi + MUPAR)</strong> untuk penjangkauan segera.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/screening/create" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/20 transition transform active:scale-98">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Skrining PUS Baru
            </a>
        </div>
    </div>

    <!-- Flash Alert -->
    @if (session()->has('success'))
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800/60 dark:text-emerald-300 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="text-sm font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- 4 TailAdmin Priority Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        
        <!-- Total Antrean Aktif -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                        Total Antrean
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ number_format($totalAntrean) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-600 dark:text-slate-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span class="inline-block w-2 h-2 rounded-full bg-slate-400"></span>
                <span>Seluruh hasil skrining tercatat</span>
            </div>
        </div>

        <!-- #1 Prioritas Utama (Skor 11-15 + MUPAR) HIGHLIGHT -->
        <div class="p-5 rounded-2xl bg-gradient-to-br from-red-50 to-purple-50 dark:from-red-950/40 dark:to-purple-950/40 border-2 border-red-500/50 shadow-md relative overflow-hidden">
            <div class="absolute top-2 right-2 px-1.5 py-0.5 rounded text-[9px] font-black bg-red-600 text-white uppercase tracking-wider">
                URUTAN #1
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold tracking-wider text-red-700 dark:text-red-300 uppercase">
                        Prioritas Utama (MUPAR)
                    </p>
                    <h3 class="mt-1 text-2xl font-black text-red-900 dark:text-red-100">
                        {{ number_format($countPrioritasUtama) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-red-500 text-white flex items-center justify-center shadow-md shadow-red-500/30">
                    <svg class="w-6 h-6 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-red-800 dark:text-red-200">
                <span class="inline-block w-2 h-2 rounded-full bg-red-600 animate-ping"></span>
                <span>Skor 11–15 &bull; Kunjungan Segera!</span>
            </div>
        </div>

        <!-- #2 Prioritas Tinggi (Skor 11-15 + Non-MUPAR) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-red-200 dark:border-red-900/60 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-red-700 dark:text-red-400 uppercase">
                        Prioritas Tinggi
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ number_format($countPrioritasTinggi) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-red-100 dark:bg-red-950/60 flex items-center justify-center text-red-700 dark:text-red-300">
                    <span class="text-xs font-black">#2</span>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span>Skor 11–15 (Non-MUPAR)</span>
            </div>
        </div>

        <!-- #3 Prioritas Edukasi Terjadwal (Skor 7-10 + MUPAR) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-900/60 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-purple-700 dark:text-purple-300 uppercase">
                        Edukasi Terjadwal (MUPAR)
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-purple-950 dark:text-purple-100">
                        {{ number_format($countEdukasiTerjadwal) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-purple-100 dark:bg-purple-950/60 flex items-center justify-center text-purple-700 dark:text-purple-300">
                    <span class="text-xs font-black">#3</span>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-purple-700 dark:text-purple-300 font-medium">
                <span>Skor 7–10 (Fokus MUPAR)</span>
            </div>
        </div>

    </div>

    <!-- Toolbar Filter & Search -->
    <div class="mb-6 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
            
            <!-- Search -->
            <div class="sm:col-span-2 xl:col-span-2 relative min-w-0">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Cari nama istri, suami, atau alamat..."
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
            </div>

            <!-- Filter Tingkat Prioritas / Urutan Antrean -->
            <div class="min-w-0">
                <select wire:model.live="filter_urutan"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] transition truncate">
                    <option value="">Semua Tingkat Prioritas</option>
                    <option value="1">#1 Prioritas Utama (Skor 11-15 + MUPAR)</option>
                    <option value="2">#2 Prioritas Tinggi (Skor 11-15 + Non-MUPAR)</option>
                    <option value="3">#3 Prioritas Edukasi (Skor 7-10 + MUPAR)</option>
                    <option value="4">#4 Prioritas Sedang (Skor 7-10 + Non-MUPAR)</option>
                    <option value="5">#5 Monitoring Rutin (Skor 0-6)</option>
                </select>
            </div>

            <!-- Filter Kecamatan -->
            <div class="min-w-0">
                <select wire:model.live="kecamatan_id"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] transition truncate">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatans as $kec)
                        <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter MUPAR Only -->
            <div class="min-w-0">
                <select wire:model.live="filter_mupar"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] transition truncate">
                    <option value="">Semua Status MUPAR</option>
                    <option value="1">Hanya Target MUPAR</option>
                    <option value="0">Hanya Non-MUPAR</option>
                </select>
            </div>

        </div>

        @if($search || $filter_urutan !== '' || $filter_mupar !== '' || $kecamatan_id)
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">
                    Menampilkan hasil filter antrean khusus
                </span>
                <button wire:click="resetFilters" type="button" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                    Reset Filter
                </button>
            </div>
        @endif
    </div>

    <!-- Table TailAdmin: Prioritas Queue List -->
    <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full min-w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="w-14 px-3 py-3.5 text-center">Urutan</th>
                        <th class="px-4 py-3.5 min-w-[190px]">Pasangan PUS</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Wilayah Binaan</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Total Skor</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Ketetapan Prioritas</th>
                        <th class="px-4 py-3.5 min-w-[200px] max-w-xs">Rekomendasi Tindakan</th>
                        <th class="px-3 py-3.5 whitespace-nowrap">Tgl Skrining</th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap min-w-[160px]">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($queue as $item)
                        @php
                            $pus = $item->pusProfile;
                            $isTopPriority = $item->urutan_antrean === 1;
                        @endphp
                        <tr wire:key="queue-{{ $item->id }}"
                            class="transition {{ $isTopPriority ? 'bg-red-50/30 dark:bg-red-950/20 hover:bg-red-50/60 dark:hover:bg-red-950/40' : 'hover:bg-slate-50/60 dark:hover:bg-slate-750' }}">
                            
                            <!-- Urutan Rank Badge -->
                            <td class="px-3 py-3.5 text-center whitespace-nowrap">
                                @if($item->urutan_antrean === 1)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-red-600 text-white shadow-md shadow-red-600/30 ring-2 ring-red-400/50">
                                        #1
                                    </span>
                                @elseif($item->urutan_antrean === 2)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300 border border-red-300 dark:border-red-800">
                                        #2
                                    </span>
                                @elseif($item->urutan_antrean === 3)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-300 border border-purple-300 dark:border-purple-800">
                                        #3
                                    </span>
                                @elseif($item->urutan_antrean === 4)
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-800">
                                        #4
                                    </span>
                                @else
                                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                                        #5
                                    </span>
                                @endif
                            </td>

                            <!-- Pasangan PUS -->
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5 flex-wrap">
                                    <span>Ny. {{ $pus?->nama_istri ?? '-' }}</span>
                                    @if($item->is_mupar)
                                        <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-300 dark:bg-purple-950 dark:text-purple-300">
                                            MUPAR
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Tn. {{ $pus?->nama_suami ?? '-' }} &bull; Usia: {{ $pus?->usiaIstri() }} th &bull; Anak: {{ $pus?->jumlah_anak_lahir_hidup }}
                                </div>
                            </td>

                            <!-- Wilayah -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <div class="font-medium text-slate-900 dark:text-white text-xs sm:text-sm">
                                    {{ $pus?->desa?->name ?? '-' }}
                                </div>
                                <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Kec. {{ $pus?->kecamatan?->name ?? '-' }}
                                </div>
                            </td>

                            <!-- Total Skor -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-sm font-extrabold {{ $item->total_skor >= 11 ? 'text-red-600 dark:text-red-400' : ($item->total_skor >= 7 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400') }}">
                                        {{ $item->total_skor }}
                                    </span>
                                    <span class="text-[11px] text-slate-400">/ 15</span>
                                </div>
                                <!-- Micro Progress Bar -->
                                <div class="w-16 sm:w-20 h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 mt-1 overflow-hidden">
                                    <div class="h-full rounded-full {{ $item->total_skor >= 11 ? 'bg-red-500' : ($item->total_skor >= 7 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                         style="width: {{ ($item->total_skor / 15) * 100 }}%">
                                    </div>
                                </div>
                            </td>

                            <!-- Ketetapan Prioritas Akhir -->
                            <td class="px-3 py-3.5 whitespace-nowrap">
                                @php
                                    $badgeColor = match($item->urutan_antrean) {
                                        1 => 'bg-red-600 text-white font-black shadow-xs shadow-red-500/40',
                                        2 => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950 dark:text-red-300 font-bold',
                                        3 => 'bg-purple-100 text-purple-800 border-purple-300 dark:bg-purple-950 dark:text-purple-300 font-bold',
                                        4 => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950 dark:text-amber-300 font-bold',
                                        default => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-300 font-semibold',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded-md text-[11px] border {{ $badgeColor }}">
                                    {{ $item->prioritas_akhir }}
                                </span>
                            </td>

                            <!-- Rekomendasi Tindakan PKB -->
                            <td class="px-4 py-3.5 text-xs">
                                <p class="text-slate-700 dark:text-slate-300 leading-snug">
                                    @if($item->urutan_antrean === 1)
                                        <strong class="text-red-600 dark:text-red-400 font-bold">Prioritas utama:</strong> Penjangkauan &amp; konseling segera!
                                    @elseif($item->urutan_antrean === 2)
                                        Prioritas penjangkauan &amp; konseling segera
                                    @elseif($item->urutan_antrean === 3)
                                        Penjangkauan / edukasi terjadwal (Fokus MUPAR)
                                    @elseif($item->urutan_antrean === 4)
                                        Penjangkauan &amp; konseling terjadwal
                                    @else
                                        Monitoring &amp; edukasi rutin
                                    @endif
                                </p>
                            </td>

                            <!-- Tgl Skrining -->
                            <td class="px-3 py-3.5 whitespace-nowrap text-xs text-slate-500 dark:text-slate-400">
                                {{ $item->tanggal_skrining->format('d M Y') }}
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button wire:click="openDetailModal({{ $item->id }})"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600 transition"
                                            title="Lihat Rincian Skor Skrining">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                        <span>Rincian</span>
                                    </button>

                                    <button wire:click="$dispatch('open-intervention-modal', { screeningId: {{ $item->id }} })"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-xs shadow-[#008060]/30 transition"
                                            title="Catat Realisasi Intervensi &amp; Konseling">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span>Intervensi</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="max-w-sm mx-auto text-center">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-400">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </div>
                                    <h4 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">
                                        Belum ada antrean skrining
                                    </h4>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Lakukan skrining pada sasaran PUS untuk memunculkan antrean prioritas.
                                    </p>
                                    <a href="/screening/create" wire:navigate
                                       class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52]">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Mulai Skrining Sekarang
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($queue->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $queue->links() }}
            </div>
        @endif
    </div>

    <!-- MODAL DETAIL SKRINING -->
    @if($showDetailModal && $activeScreening)
        <div class="fixed inset-0 z-999999 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="w-full max-w-xl bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden mx-auto" style="max-width: 576px;">
                
                <!-- Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-slate-50/60 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold">
                            #{{ $activeScreening->urutan_antrean }}
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Lembar Hasil Skrining Lima Indikator
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Ny. {{ $activeScreening->pusProfile?->nama_istri }} / Tn. {{ $activeScreening->pusProfile?->nama_suami }}
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

                <!-- Body Breakdown -->
                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto">
                    
                    <!-- Result Summary Banner -->
                    <div class="p-4 rounded-2xl {{ $activeScreening->urutan_antrean === 1 ? 'bg-red-500 text-white' : ($activeScreening->urutan_antrean <= 3 ? 'bg-purple-900 text-white' : 'bg-slate-900 text-white') }} flex items-center justify-between shadow-md">
                        <div>
                            <span class="text-[11px] uppercase tracking-wider opacity-80">
                                Ketetapan Prioritas &amp; Urutan
                            </span>
                            <h4 class="text-lg font-black tracking-tight">
                                {{ $activeScreening->prioritas_akhir }}
                            </h4>
                            <p class="text-xs opacity-90 mt-0.5">
                                Kategori: {{ ucfirst($activeScreening->kategori->value) }} &bull; Urutan #{{ $activeScreening->urutan_antrean }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="text-3xl font-black">
                                {{ $activeScreening->total_skor }}
                            </span>
                            <span class="text-xs opacity-70">/ 15</span>
                        </div>
                    </div>

                    <!-- MUPAR status card -->
                    <div class="p-3.5 rounded-xl border {{ $activeScreening->is_mupar ? 'bg-purple-50 border-purple-200 text-purple-900 dark:bg-purple-950/40 dark:border-purple-800' : 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700' }} flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="font-bold">Status MUPAR:</span>
                            @if($activeScreening->is_mupar)
                                <span class="px-2 py-0.5 rounded font-bold bg-purple-200 text-purple-900 dark:bg-purple-900 dark:text-purple-200">
                                    YA (Muda Paritas Rendah)
                                </span>
                            @else
                                <span class="text-slate-500">Bukan MUPAR</span>
                            @endif
                        </div>
                        <span class="text-[11px] text-slate-500">
                            (Usia ≤ 35 th &amp; Anak ≤ 2)
                        </span>
                    </div>

                    <!-- 5 Indikator Scores Breakdown Table -->
                    <div>
                        <h5 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-2">
                            Rincian Parameter Skor SI-MENTILIN:
                        </h5>
                        <div class="rounded-xl border border-slate-200 dark:border-slate-700 divide-y divide-slate-100 dark:divide-slate-800 text-xs">
                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">P1: Usia Istri</p>
                                    <p class="text-[11px] text-slate-500">Dihitung otomatis dari tanggal lahir</p>
                                </div>
                                <span class="font-bold text-sm text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->p1_usia_istri }} / 3
                                </span>
                            </div>

                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">P2: Jumlah Anak Lahir Hidup</p>
                                    <p class="text-[11px] text-slate-500">Dihitung otomatis dari profil PUS</p>
                                </div>
                                <span class="font-bold text-sm text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->p2_jumlah_anak }} / 3
                                </span>
                            </div>

                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">P3: Status Kontrasepsi Sesuai Tujuan</p>
                                    <p class="text-[11px] text-slate-500">Tingkat kepatuhan dan kesesuaian metode KB</p>
                                </div>
                                <span class="font-bold text-sm text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->p3_status_kontrasepsi }} / 3
                                </span>
                            </div>

                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">P4: Tujuan Reproduksi Saat Ini</p>
                                    <p class="text-[11px] text-slate-500">Rencana kehamilan dan penjarangan kelahiran</p>
                                </div>
                                <span class="font-bold text-sm text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->p4_tujuan_reproduksi }} / 3
                                </span>
                            </div>

                            <div class="p-3 flex items-center justify-between">
                                <div>
                                    <p class="font-semibold text-slate-900 dark:text-white">P5: Risiko Kehamilan Tidak Direncanakan</p>
                                    <p class="text-[11px] text-slate-500">Kerentanan terjadinya kehamilan tidak diharapkan</p>
                                </div>
                                <span class="font-bold text-sm text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->p5_risiko_kehamilan }} / 3
                                </span>
                            </div>

                            <div class="p-3 bg-slate-50 dark:bg-slate-900/60 flex items-center justify-between font-bold">
                                <span class="text-slate-900 dark:text-white">Total Skor SI-MENTILIN (P1 + P2 + P3 + P4 + P5)</span>
                                <span class="text-base text-[#008060] dark:text-emerald-400">
                                    {{ $activeScreening->total_skor }} / 15
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    @if($activeScreening->catatan)
                        <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/50 text-xs">
                            <span class="font-bold text-amber-900 dark:text-amber-300 block mb-1">
                                Catatan Wawancara Lapangan:
                            </span>
                            <p class="text-amber-800 dark:text-amber-200">
                                {{ $activeScreening->catatan }}
                            </p>
                        </div>
                    @endif

                    <div class="text-[11px] text-slate-400 flex items-center justify-between pt-2">
                        <span>Petugas Skrining: {{ $activeScreening->user?->name ?? 'Petugas Lapangan' }}</span>
                        <span>Tgl: {{ $activeScreening->tanggal_skrining->format('d F Y') }}</span>
                    </div>

                </div>

                <!-- Footer -->
                <div class="px-6 py-4 bg-slate-50/60 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between">
                    <button wire:click="closeDetailModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 hover:bg-slate-50 transition">
                        Tutup
                    </button>

                    <button wire:click="$dispatch('open-intervention-modal', { screeningId: {{ $activeScreening->id }} }); closeDetailModal()"
                            type="button"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/30 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        <span>Catat Intervensi Sekarang</span>
                    </button>
                </div>

            </div>
        </div>
    @endif

    <!-- Livewire 4 Modal Pencatatan Intervensi -->
    <livewire:intervention-modal />
</div>