<?php

use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Master Wilayah | SI-MENTILIN')] class extends Component
{
    use WithPagination;

    #[Url(as: 'tab')]
    public string $activeTab = 'kecamatan'; // 'kecamatan' or 'desa'

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kec')]
    public ?int $filter_kecamatan_id = null;

    // Modals
    public bool $showDesaModal = false;
    public bool $showKecamatanModal = false;
    public bool $showDeleteDesaModal = false;

    // Form inputs for Desa
    public ?int $editingDesaId = null;
    public ?int $desa_kecamatan_id = null;
    public string $desa_name = '';
    public string $desa_code = '';

    // Form inputs for Kecamatan
    public ?int $editingKecamatanId = null;
    public string $kecamatan_name = '';
    public string $kecamatan_code = '';

    // Deleting state
    public ?int $deletingDesaId = null;
    public string $deletingDesaName = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKecamatanId(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function selectKecamatanForDesas(int $kecamatanId): void
    {
        $this->filter_kecamatan_id = $kecamatanId;
        $this->activeTab = 'desa';
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter_kecamatan_id = null;
        $this->resetPage();
    }

    // ──────────────── DESA CRUD ────────────────
    public function openCreateDesaModal(): void
    {
        $this->resetValidation();
        $this->editingDesaId = null;
        $this->desa_kecamatan_id = $this->filter_kecamatan_id ?? Kecamatan::first()?->id;
        $this->desa_name = '';

        // Auto suggestion for code
        $kec = Kecamatan::find($this->desa_kecamatan_id);
        $this->desa_code = $kec ? $kec->code . '.20' . str_pad((string) (Desa::where('kecamatan_id', $kec->id)->count() + 1), 2, '0', STR_PAD_LEFT) : '';

        $this->showDesaModal = true;
    }

    public function openEditDesaModal(int $desaId): void
    {
        $this->resetValidation();
        $desa = Desa::findOrFail($desaId);

        $this->editingDesaId = $desa->id;
        $this->desa_kecamatan_id = $desa->kecamatan_id;
        $this->desa_name = $desa->name;
        $this->desa_code = $desa->code;

        $this->showDesaModal = true;
    }

    public function saveDesa(): void
    {
        $validated = $this->validate([
            'desa_kecamatan_id' => 'required|exists:kecamatans,id',
            'desa_name' => 'required|string|max:100',
            'desa_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('desas', 'code')->ignore($this->editingDesaId),
            ],
        ], [
            'desa_kecamatan_id.required' => 'Kecamatan wajib dipilih.',
            'desa_name.required' => 'Nama desa/kelurahan wajib diisi.',
            'desa_code.required' => 'Kode wilayah BPS wajib diisi.',
            'desa_code.unique' => 'Kode wilayah ini sudah terdaftar.',
        ]);

        if ($this->editingDesaId) {
            $desa = Desa::findOrFail($this->editingDesaId);
            $desa->update([
                'kecamatan_id' => $validated['desa_kecamatan_id'],
                'name' => $validated['desa_name'],
                'code' => $validated['desa_code'],
            ]);
            session()->flash('success', "Desa/Kelurahan '{$desa->name}' berhasil diperbarui.");
        } else {
            $desa = Desa::create([
                'kecamatan_id' => $validated['desa_kecamatan_id'],
                'name' => $validated['desa_name'],
                'code' => $validated['desa_code'],
            ]);
            session()->flash('success', "Desa/Kelurahan '{$desa->name}' berhasil ditambahkan ke wilayah.");
        }

        $this->showDesaModal = false;
    }

    public function confirmDeleteDesa(int $desaId): void
    {
        $desa = Desa::withCount('pusProfiles')->findOrFail($desaId);

        if ($desa->pus_profiles_count > 0) {
            session()->flash('error', "Desa '{$desa->name}' tidak dapat dihapus karena memiliki {$desa->pus_profiles_count} data profil PUS terdaftar.");
            return;
        }

        $this->deletingDesaId = $desa->id;
        $this->deletingDesaName = $desa->name;
        $this->showDeleteDesaModal = true;
    }

    public function deleteDesa(): void
    {
        $desa = Desa::withCount('pusProfiles')->findOrFail($this->deletingDesaId);

        if ($desa->pus_profiles_count > 0) {
            session()->flash('error', "Desa '{$desa->name}' tidak dapat dihapus karena memiliki data PUS.");
            $this->showDeleteDesaModal = false;
            return;
        }

        $desaName = $desa->name;
        $desa->delete();

        $this->showDeleteDesaModal = false;
        session()->flash('success', "Desa/Kelurahan '{$desaName}' berhasil dihapus.");
    }

    // ──────────────── KECAMATAN EDIT ────────────────
    public function openEditKecamatanModal(int $kecamatanId): void
    {
        $this->resetValidation();
        $kec = Kecamatan::findOrFail($kecamatanId);

        $this->editingKecamatanId = $kec->id;
        $this->kecamatan_name = $kec->name;
        $this->kecamatan_code = $kec->code;

        $this->showKecamatanModal = true;
    }

    public function saveKecamatan(): void
    {
        $validated = $this->validate([
            'kecamatan_name' => 'required|string|max:100',
            'kecamatan_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kecamatans', 'code')->ignore($this->editingKecamatanId),
            ],
        ], [
            'kecamatan_name.required' => 'Nama kecamatan wajib diisi.',
            'kecamatan_code.required' => 'Kode wilayah kecamatan wajib diisi.',
            'kecamatan_code.unique' => 'Kode wilayah kecamatan ini sudah digunakan.',
        ]);

        $kec = Kecamatan::findOrFail($this->editingKecamatanId);
        $kec->update([
            'name' => $validated['kecamatan_name'],
            'code' => $validated['kecamatan_code'],
        ]);

        $this->showKecamatanModal = false;
        session()->flash('success', "Data Kecamatan '{$kec->name}' berhasil diperbarui.");
    }

    public function with(): array
    {
        // Global Statistics
        $totalKecamatan = Kecamatan::count();
        $totalDesa = Desa::count();
        $totalPus = PusProfile::count();
        $totalPetugas = User::where('role', \App\Enums\UserRole::PETUGAS_LAPANGAN)->count();

        // Kecamatan with aggregated metrics
        $kecamatans = Kecamatan::withCount([
            'desas',
            'users' => fn ($q) => $q->where('role', \App\Enums\UserRole::PETUGAS_LAPANGAN),
            'pusProfiles',
            'screenings',
        ])->orderBy('name')->get();

        // Desas query with pagination
        $desaQuery = Desa::query()->with(['kecamatan', 'pusProfiles'])->withCount('pusProfiles');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $desaQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('code', 'like', $term);
            });
        }

        if ($this->filter_kecamatan_id) {
            $desaQuery->where('kecamatan_id', $this->filter_kecamatan_id);
        }

        $desas = $desaQuery->orderBy('kecamatan_id')->orderBy('name')->paginate(12);

        $allKecamatans = Kecamatan::orderBy('name')->get();

        return compact(
            'totalKecamatan',
            'totalDesa',
            'totalPus',
            'totalPetugas',
            'kecamatans',
            'desas',
            'allKecamatans'
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
                    <span>Master Data Wilayah DP2KBP3A Kabupaten Bangka</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Master Wilayah &amp; Pemetaan Sasaran
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Struktur administratif 8 Kecamatan dan 62 Desa/Kelurahan se-Kabupaten Bangka. Pantau rasio penugasan Petugas Lapangan (PKB/PLKB), sebaran PUS, dan capaian skrining per wilayah binaan.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
                <button wire:click="openCreateDesaModal"
                        type="button"
                        class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-[#008060] to-[#10B981] hover:from-[#006e52] hover:to-[#059669] text-white font-bold text-sm shadow-xl shadow-black/20 transition transform active:scale-95 focus:outline-none ring-2 ring-white/30">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Tambah Desa / Kelurahan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if(session()->has('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-300 dark:border-emerald-700/50 text-emerald-800 dark:text-emerald-200 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-300 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold">{{ session('success') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-emerald-600 dark:text-emerald-400 hover:opacity-75">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/40 border border-red-300 dark:border-red-700/50 text-red-800 dark:text-red-200 flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-red-500/20 text-red-600 dark:text-red-300 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <span class="text-sm font-semibold">{{ session('error') }}</span>
            </div>
            <button type="button" @click="$el.parentElement.remove()" class="text-red-600 dark:text-red-400 hover:opacity-75">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         STATISTIC CARDS
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Kecamatan -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Kecamatan</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ $totalKecamatan }} <span class="text-sm font-medium text-slate-400">Kec.</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[#0B2E59] dark:text-blue-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>Wilayah administratif Kab. Bangka</span>
            </p>
        </div>

        <!-- Total Desa -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Desa &amp; Kelurahan</p>
                    <h3 class="text-2xl font-black text-[#008060] dark:text-emerald-400 mt-1">{{ $totalDesa }} <span class="text-sm font-medium text-slate-400">Desa</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Titik fokus intervensi PKB/PLKB</span>
            </p>
        </div>

        <!-- Total Petugas Lapangan -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Petugas Lapangan (PKB)</p>
                    <h3 class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ $totalPetugas }} <span class="text-sm font-medium text-slate-400">Petugas</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Terdistribusi di 8 Kecamatan</span>
            </p>
        </div>

        <!-- Total PUS -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total PUS Terdata</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalPus) }} <span class="text-sm font-medium text-slate-400">PUS</span></h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span>Basis sasaran skrining 5 indikator</span>
            </p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         NAVIGATION TABS
         ════════════════════════════════════════════════════════════ -->
    <div class="flex items-center gap-3 border-b border-slate-200 dark:border-slate-700">
        <button wire:click="setTab('kecamatan')"
                type="button"
                class="pb-3 px-2 text-sm font-bold flex items-center gap-2 border-b-2 transition {{ $activeTab === 'kecamatan' ? 'border-[#008060] text-[#008060] dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <span>8 Kecamatan (Ringkasan Wilayah)</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-700 font-semibold">{{ $totalKecamatan }}</span>
        </button>

        <button wire:click="setTab('desa')"
                type="button"
                class="pb-3 px-2 text-sm font-bold flex items-center gap-2 border-b-2 transition {{ $activeTab === 'desa' ? 'border-[#008060] text-[#008060] dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            </svg>
            <span>Daftar Desa / Kelurahan</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 dark:bg-slate-700 font-semibold">{{ $totalDesa }}</span>
        </button>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         TAB 1: 8 KECAMATAN GRID & DETAILS
         ════════════════════════════════════════════════════════════ -->
    @if($activeTab === 'kecamatan')
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
            @foreach($kecamatans as $kec)
                @php
                    $coverageRate = $kec->pus_profiles_count > 0 
                        ? round(($kec->screenings_count / $kec->pus_profiles_count) * 100) 
                        : 0;
                @endphp
                <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs hover:shadow-md transition flex flex-col justify-between group">
                    <div>
                        <!-- Header Card -->
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                    {{ $kec->code }}
                                </span>
                                <h4 class="text-base font-extrabold text-slate-900 dark:text-white mt-1.5 group-hover:text-[#008060] dark:group-hover:text-emerald-400 transition">
                                    Kec. {{ $kec->name }}
                                </h4>
                            </div>
                            <button wire:click="openEditKecamatanModal({{ $kec->id }})"
                                    type="button"
                                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                    title="Edit Kode/Nama Kecamatan">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Statistics Breakdown -->
                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-750">
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">Desa/Kelurahan</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $kec->desas_count }} Desa</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-750">
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">Petugas PKB</p>
                                <p class="text-sm font-bold text-amber-600 dark:text-amber-400 mt-0.5">{{ $kec->users_count }} Orang</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-750">
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">PUS Terdata</p>
                                <p class="text-sm font-bold text-[#008060] dark:text-emerald-400 mt-0.5">{{ $kec->pus_profiles_count }} PUS</p>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-100 dark:border-slate-750">
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">Diskrining</p>
                                <p class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $kec->screenings_count }} PUS</p>
                            </div>
                        </div>

                        <!-- Coverage Bar -->
                        <div class="mt-4">
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="text-slate-500 dark:text-slate-400">Cakupan Skrining</span>
                                <span class="font-bold {{ $coverageRate >= 70 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-700 dark:text-slate-300' }}">{{ $coverageRate }}%</span>
                            </div>
                            <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-[#008060] to-[#10B981] transition-all duration-500"
                                     style="width: {{ min(100, $coverageRate) }}%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Action -->
                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-700/60">
                        <button wire:click="selectKecamatanForDesas({{ $kec->id }})"
                                type="button"
                                class="w-full inline-flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl bg-slate-100 hover:bg-[#008060] hover:text-white dark:bg-slate-700/60 dark:hover:bg-[#008060] text-slate-700 dark:text-slate-200 text-xs font-bold transition">
                            <span>Kelola {{ $kec->desas_count }} Desa</span>
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         TAB 2: DESA / KELURAHAN TABLE
         ════════════════════════════════════════════════════════════ -->
    @if($activeTab === 'desa')
        <div class="space-y-4">
            
            <!-- Filter Bar -->
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
                    
                    <!-- Search -->
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <input wire:model.live.debounce.300ms="search"
                               type="text"
                               placeholder="Cari desa/kelurahan atau kode BPS..."
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    </div>

                    <!-- Kecamatan Filter -->
                    <div>
                        <select wire:model.live="filter_kecamatan_id"
                                class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            <option value="">Semua Kecamatan (8 Kec.)</option>
                            @foreach($allKecamatans as $k)
                                <option value="{{ $k->id }}">Kec. {{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Reset & Add button -->
                    <div class="flex items-center gap-2">
                        <button wire:click="resetFilters"
                                type="button"
                                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Reset</span>
                        </button>

                        <button wire:click="openCreateDesaModal"
                                type="button"
                                class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Tambah Desa</span>
                        </button>
                    </div>

                </div>
            </div>

            <!-- Table of Desas -->
            <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                <th class="py-4 px-6">Nama Desa / Kelurahan</th>
                                <th class="py-4 px-6">Kode Wilayah (BPS)</th>
                                <th class="py-4 px-6">Kecamatan</th>
                                <th class="py-4 px-6 text-center">Sasaran PUS</th>
                                <th class="py-4 px-6 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs text-slate-700 dark:text-slate-300">
                            @forelse($desas as $desa)
                                <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition">
                                    <td class="py-4 px-6">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-400 flex items-center justify-center font-bold">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                </svg>
                                            </div>
                                            <span class="font-bold text-slate-900 dark:text-white text-sm">
                                                {{ $desa->name }}
                                            </span>
                                        </div>
                                    </td>

                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-md font-mono text-xs font-semibold bg-slate-100 dark:bg-slate-700/60 text-slate-700 dark:text-slate-300">
                                            {{ $desa->code }}
                                        </span>
                                    </td>

                                    <td class="py-4 px-6">
                                        <span class="inline-flex items-center gap-1.5 font-semibold text-slate-800 dark:text-slate-200">
                                            <span class="w-2 h-2 rounded-full bg-[#008060]"></span>
                                            <span>Kec. {{ $desa->kecamatan?->name }}</span>
                                        </span>
                                    </td>

                                    <td class="py-4 px-6 text-center">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-xl text-xs font-bold {{ $desa->pus_profiles_count > 0 ? 'bg-emerald-50 text-[#008060] dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-500 dark:bg-slate-700/50 dark:text-slate-400' }}">
                                            {{ $desa->pus_profiles_count }} PUS
                                        </span>
                                    </td>

                                    <td class="py-4 px-6 text-right">
                                        <div class="inline-flex items-center gap-1.5">
                                            <!-- Edit Desa -->
                                            <button wire:click="openEditDesaModal({{ $desa->id }})"
                                                    type="button"
                                                    class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                                    title="Edit Data Desa">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>

                                            <!-- Delete Desa -->
                                            <button wire:click="confirmDeleteDesa({{ $desa->id }})"
                                                    type="button"
                                                    class="p-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                    title="Hapus Desa">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            </svg>
                                            <p class="font-semibold text-sm">Tidak ada desa/kelurahan yang cocok dengan pencarian.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($desas->hasPages())
                    <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                        {{ $desas->links() }}
                    </div>
                @endif
            </div>

        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 1: TAMBAH / EDIT DESA
         ════════════════════════════════════════════════════════════ -->
    @if($showDesaModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-desa-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showDesaModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 500px;">
                    
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-desa-title">
                                {{ $editingDesaId ? 'Edit Desa / Kelurahan' : 'Tambah Desa / Kelurahan Baru' }}
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Master wilayah administratif tingkat desa di Kabupaten Bangka.
                            </p>
                        </div>
                        <button wire:click="$set('showDesaModal', false)"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="saveDesa" class="space-y-4 mt-5">
                        
                        <!-- Kecamatan -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Kecamatan Induk <span class="text-red-500">*</span>
                            </label>
                            <select wire:model="desa_kecamatan_id"
                                    class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                                <option value="">-- Pilih Kecamatan --</option>
                                @foreach($allKecamatans as $k)
                                    <option value="{{ $k->id }}">Kec. {{ $k->name }} ({{ $k->code }})</option>
                                @endforeach
                            </select>
                            @error('desa_kecamatan_id') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Nama Desa -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Desa / Kelurahan <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="desa_name"
                                   type="text"
                                   placeholder="Contoh: Kudai / Sungailiat / Baturusa"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('desa_name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Kode BPS -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Kode Wilayah BPS <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="desa_code"
                                   type="text"
                                   placeholder="Contoh: 19.01.01.2001"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            <p class="text-[11px] text-slate-400 mt-1">Format kode wilayah standar: 19.01.XX.YYYY</p>
                            @error('desa_code') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="$set('showDesaModal', false)"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow-lg shadow-[#008060]/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>{{ $editingDesaId ? 'Simpan Perubahan' : 'Tambahkan Desa' }}</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 2: EDIT KECAMATAN
         ════════════════════════════════════════════════════════════ -->
    @if($showKecamatanModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-kec-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showKecamatanModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-7 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 480px;">
                    
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white" id="modal-kec-title">
                                Edit Data Kecamatan
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Perbarui nama atau kode resmi kecamatan.
                            </p>
                        </div>
                        <button wire:click="$set('showKecamatanModal', false)"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="saveKecamatan" class="space-y-4 mt-5">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Kecamatan <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="kecamatan_name"
                                   type="text"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('kecamatan_name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Kode Kecamatan BPS <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="kecamatan_code"
                                   type="text"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('kecamatan_code') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="$set('showKecamatanModal', false)"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow-lg shadow-[#008060]/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                                <span>Simpan Perubahan</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 3: HAPUS DESA CONFIRMATION
         ════════════════════════════════════════════════════════════ -->
    @if($showDeleteDesaModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-del-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showDeleteDesaModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all text-center mx-auto" style="max-width: 440px;">
                    
                    <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 mx-auto flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-del-title">
                        Hapus Desa / Kelurahan?
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                        Apakah Anda yakin ingin menghapus desa <strong class="text-slate-900 dark:text-white">{{ $deletingDesaName }}</strong>? Desa hanya dapat dihapus jika tidak memiliki data sasaran PUS terdaftar.
                    </p>

                    <div class="flex items-center justify-center gap-3 mt-6">
                        <button wire:click="$set('showDeleteDesaModal', false)"
                                type="button"
                                class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                            Batalkan
                        </button>
                        <button wire:click="deleteDesa"
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold shadow-lg shadow-red-600/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Ya, Hapus Desa</span>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
