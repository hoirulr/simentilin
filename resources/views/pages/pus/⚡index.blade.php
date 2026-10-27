<?php

use App\Models\AuditLog;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Services\ScreeningCalculatorService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kec')]
    public ?int $kecamatan_id = null;

    #[Url(as: 'desa')]
    public ?int $desa_id = null;

    #[Url(as: 'mupar')]
    public string $filter_mupar = '';

    #[Url(as: 'status')]
    public string $filter_skrining = '';

    // Modal state
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showDetailModal = false;
    public ?int $editing_pus_id = null;
    public ?int $detail_pus_id = null;

    // Form inputs for creating PUS
    public string $nama_istri = '';
    public string $nama_suami = '';
    public string $tanggal_lahir_istri = '';
    public int $jumlah_anak_lahir_hidup = 0;
    public ?int $new_kecamatan_id = null;
    public ?int $new_desa_id = null;
    public string $rt_rw = '';
    public string $alamat = '';

    // Form inputs for editing PUS
    public string $edit_nama_istri = '';
    public string $edit_nama_suami = '';
    public string $edit_tanggal_lahir_istri = '';
    public int $edit_jumlah_anak_lahir_hidup = 0;
    public ?int $edit_kecamatan_id = null;
    public ?int $edit_desa_id = null;
    public string $edit_rt_rw = '';
    public string $edit_alamat = '';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user && ! $user->isAdminDinas()) {
            $this->kecamatan_id = $user->kecamatan_id;
            $this->new_kecamatan_id = $user->kecamatan_id;
            if ($user->desa_id) {
                $this->desa_id = $user->desa_id;
                $this->new_desa_id = $user->desa_id;
            }
        } else {
            $firstKec = Kecamatan::first();
            $this->new_kecamatan_id = $firstKec?->id;
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

    public function updatedFilterMupar(): void
    {
        $this->resetPage();
    }

    public function updatedFilterSkrining(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $user = auth()->user();
        if (! $user || $user->isAdminDinas()) {
            $this->kecamatan_id = null;
            $this->desa_id = null;
        } else {
            $this->desa_id = null;
        }
        $this->filter_mupar = '';
        $this->filter_skrining = '';
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', PusProfile::class);
        $this->resetValidation();
        $this->nama_istri = '';
        $this->nama_suami = '';
        $this->tanggal_lahir_istri = now()->subYears(26)->format('Y-m-d');
        $this->jumlah_anak_lahir_hidup = 1;
        $this->rt_rw = '';
        $this->alamat = '';

        $user = auth()->user();
        if ($user && ! $user->isAdminDinas()) {
            $this->new_kecamatan_id = $user->kecamatan_id;
            $this->new_desa_id = $user->desa_id;
        }

        $this->showCreateModal = true;
    }

    public function savePus(ScreeningCalculatorService $calculator): void
    {
        $this->authorize('create', PusProfile::class);

        $user = auth()->user();
        if (! $user) {
            abort(403, 'Akses tidak diizinkan. Silakan login terlebih dahulu.');
        }

        $isAdmin = $user->isAdminDinas();

        $rules = [
            'nama_istri' => 'required|string|max:150',
            'nama_suami' => 'required|string|max:150',
            'tanggal_lahir_istri' => 'required|date|before:today',
            'jumlah_anak_lahir_hidup' => 'required|integer|min:0|max:20',
            'rt_rw' => 'nullable|string|max:50',
            'alamat' => 'nullable|string|max:500',
        ];

        if ($isAdmin) {
            $rules['new_kecamatan_id'] = 'required|exists:kecamatans,id';
            $rules['new_desa_id'] = [
                'required',
                Rule::exists('desas', 'id')->where('kecamatan_id', $this->new_kecamatan_id),
            ];
        } else {
            // Petugas lapangan: wilayah terkunci dari penugasan pengguna
            $rules['new_kecamatan_id'] = 'nullable';
            if (! $user->desa_id) {
                // Koordinator kecamatan: desa harus milik kecamatan penugasannya
                $rules['new_desa_id'] = [
                    'required',
                    Rule::exists('desas', 'id')->where('kecamatan_id', $user->kecamatan_id),
                ];
            } else {
                $rules['new_desa_id'] = 'nullable';
            }
        }

        $validated = $this->validate($rules, [
            'nama_istri.required' => 'Nama istri wajib diisi.',
            'nama_suami.required' => 'Nama suami wajib diisi.',
            'tanggal_lahir_istri.required' => 'Tanggal lahir istri wajib diisi.',
            'new_kecamatan_id.required' => 'Kecamatan wajib dipilih.',
            'new_desa_id.required' => 'Desa wajib dipilih.',
            'new_desa_id.exists' => 'Desa tidak valid atau bukan bagian dari kecamatan yang dipilih.',
        ]);

        $kecamatanId = $isAdmin ? (int) $validated['new_kecamatan_id'] : (int) $user->kecamatan_id;
        $desaId = $isAdmin
            ? (int) $validated['new_desa_id']
            : ($user->desa_id ? (int) $user->desa_id : (int) ($validated['new_desa_id'] ?? $this->new_desa_id));

        $pus = PusProfile::create([
            'user_id' => $user->id,
            'kecamatan_id' => $kecamatanId,
            'desa_id' => $desaId,
            'nama_istri' => $validated['nama_istri'],
            'nama_suami' => $validated['nama_suami'],
            'tanggal_lahir_istri' => $validated['tanggal_lahir_istri'],
            'jumlah_anak_lahir_hidup' => $validated['jumlah_anak_lahir_hidup'],
            'rt_rw' => $validated['rt_rw'] ?? null,
            'alamat' => $validated['alamat'] ?? null,
        ]);

        AuditLog::log(
            'create_pus',
            "Mendaftarkan profil PUS baru: Ny. {$pus->nama_istri} / Tn. {$pus->nama_suami}",
            $pus,
            null,
            $pus->only(['nama_istri', 'nama_suami', 'kecamatan_id', 'desa_id', 'jumlah_anak_lahir_hidup'])
        );

        $this->showCreateModal = false;
        session()->flash('success', "Profil PUS Ny. {$pus->nama_istri} berhasil didaftarkan! Silakan lanjutkan skrining 5 indikator.");
    }

    public function openEditModal(int $id): void
    {
        $this->resetValidation();
        $pus = PusProfile::forCurrentUser()->findOrFail($id);
        $this->authorize('update', $pus);

        $this->editing_pus_id = $pus->id;
        $this->edit_nama_istri = $pus->nama_istri;
        $this->edit_nama_suami = $pus->nama_suami;
        $this->edit_tanggal_lahir_istri = $pus->tanggal_lahir_istri ? $pus->tanggal_lahir_istri->format('Y-m-d') : '';
        $this->edit_jumlah_anak_lahir_hidup = $pus->jumlah_anak_lahir_hidup;
        $this->edit_kecamatan_id = $pus->kecamatan_id;
        $this->edit_desa_id = $pus->desa_id;
        $this->edit_rt_rw = $pus->rt_rw ?? '';
        $this->edit_alamat = $pus->alamat ?? '';
        $this->showEditModal = true;
    }

    public function updatedEditKecamatanId(): void
    {
        $this->edit_desa_id = null;
    }

    public function updatePus(): void
    {
        $pus = PusProfile::forCurrentUser()->findOrFail($this->editing_pus_id);
        $this->authorize('update', $pus);

        $user = auth()->user();
        $isAdmin = $user && $user->isAdminDinas();

        $rules = [
            'edit_nama_istri' => 'required|string|max:150',
            'edit_nama_suami' => 'required|string|max:150',
            'edit_tanggal_lahir_istri' => 'required|date|before:today',
            'edit_jumlah_anak_lahir_hidup' => 'required|integer|min:0|max:20',
            'edit_rt_rw' => 'nullable|string|max:50',
            'edit_alamat' => 'nullable|string|max:500',
        ];

        if ($isAdmin) {
            $rules['edit_kecamatan_id'] = 'required|exists:kecamatans,id';
            $rules['edit_desa_id'] = [
                'required',
                Rule::exists('desas', 'id')->where('kecamatan_id', $this->edit_kecamatan_id),
            ];
        }

        $validated = $this->validate($rules, [
            'edit_nama_istri.required' => 'Nama istri wajib diisi.',
            'edit_nama_suami.required' => 'Nama suami wajib diisi.',
            'edit_tanggal_lahir_istri.required' => 'Tanggal lahir istri wajib diisi.',
            'edit_kecamatan_id.required' => 'Kecamatan wajib dipilih.',
            'edit_desa_id.required' => 'Desa wajib dipilih.',
            'edit_desa_id.exists' => 'Desa tidak valid atau bukan bagian dari kecamatan yang dipilih.',
        ]);

        $updateData = [
            'nama_istri' => $validated['edit_nama_istri'],
            'nama_suami' => $validated['edit_nama_suami'],
            'tanggal_lahir_istri' => $validated['edit_tanggal_lahir_istri'],
            'jumlah_anak_lahir_hidup' => $validated['edit_jumlah_anak_lahir_hidup'],
            'rt_rw' => $validated['edit_rt_rw'] ?? null,
            'alamat' => $validated['edit_alamat'] ?? null,
        ];

        // Territory is strictly immutable for field workers; admin can change only via valid relation
        if ($isAdmin) {
            $updateData['kecamatan_id'] = (int) $validated['edit_kecamatan_id'];
            $updateData['desa_id'] = (int) $validated['edit_desa_id'];
        }

        $oldValues = $pus->only(['nama_istri', 'nama_suami', 'tanggal_lahir_istri', 'jumlah_anak_lahir_hidup', 'kecamatan_id', 'desa_id', 'rt_rw', 'alamat']);
        $pus->update($updateData);

        AuditLog::log(
            'update_pus',
            "Memperbarui profil PUS Ny. {$pus->nama_istri}",
            $pus,
            $oldValues,
            $pus->only(array_keys($oldValues))
        );

        $this->showEditModal = false;
        $this->editing_pus_id = null;
        session()->flash('success', "Profil PUS Ny. {$pus->nama_istri} berhasil diperbarui!");
    }

    public function openDetailModal(int $id): void
    {
        $pus = PusProfile::forCurrentUser()->findOrFail($id);
        $this->authorize('view', $pus);

        $this->detail_pus_id = $id;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detail_pus_id = null;
    }

    public function deletePus(int $id): void
    {
        $pus = PusProfile::forCurrentUser()->findOrFail($id);
        $this->authorize('delete', $pus);

        $oldValues = $pus->toArray();
        $pusName = $pus->nama_istri;
        $pus->delete();

        AuditLog::log(
            'delete_pus',
            "Menghapus profil PUS Ny. {$pusName}",
            null,
            $oldValues,
            null
        );

        session()->flash('success', "Data profil PUS berhasil dihapus.");
    }

    public function with(): array
    {
        $calculator = app(ScreeningCalculatorService::class);
        $user = auth()->user();

        $query = PusProfile::query()
            ->forCurrentUser($user)
            ->with(['kecamatan', 'desa', 'latestScreening']);

        // Search
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('nama_istri', 'like', $term)
                  ->orWhere('nama_suami', 'like', $term)
                  ->orWhere('alamat', 'like', $term)
                  ->orWhere('rt_rw', 'like', $term);
            });
        }

        // Territory filters
        if ($this->kecamatan_id) {
            $query->where('kecamatan_id', $this->kecamatan_id);
        }
        if ($this->desa_id) {
            $query->where('desa_id', $this->desa_id);
        }

        // Screening status filter
        if ($this->filter_skrining === 'sudah') {
            $query->has('screenings');
        } elseif ($this->filter_skrining === 'belum') {
            $query->doesntHave('screenings');
        }

        // Base statistics
        $statsBase = PusProfile::query()->forCurrentUser($user);
        $totalPus = (clone $statsBase)->count();
        $sudahSkrining = (clone $statsBase)->has('screenings')->count();
        $belumSkrining = $totalPus - $sudahSkrining;

        // Count MUPAR across scoped profiles
        $allScoped = (clone $statsBase)->get(['tanggal_lahir_istri', 'jumlah_anak_lahir_hidup']);
        $totalMupar = $allScoped->filter(function ($item) use ($calculator) {
            return $calculator->determineMupar($item->usiaIstri(), $item->jumlah_anak_lahir_hidup);
        })->count();

        // Paginate results
        $pusList = $query->latest('id')->paginate(10);

        // Filter MUPAR in-memory if requested (or pre-filter)
        if ($this->filter_mupar !== '') {
            // Re-query with date condition for MUPAR (age <= 35 means born on or after today - 35 years)
            $cutoffDate = now()->subYears(35)->startOfDay()->format('Y-m-d');
            $muparQuery = clone $query;
            if ($this->filter_mupar === 'mupar') {
                $muparQuery->where('tanggal_lahir_istri', '>=', $cutoffDate)
                           ->where('jumlah_anak_lahir_hidup', '<=', 2);
            } elseif ($this->filter_mupar === 'non_mupar') {
                $muparQuery->where(function ($q) use ($cutoffDate) {
                    $q->where('tanggal_lahir_istri', '<', $cutoffDate)
                      ->orWhere('jumlah_anak_lahir_hidup', '>', 2);
                });
            }
            $pusList = $muparQuery->latest('id')->paginate(10);
        }

        // Available kecamatans & desas
        $kecamatans = Kecamatan::orderBy('name')->get();
        $desas = $this->kecamatan_id
            ? Desa::where('kecamatan_id', $this->kecamatan_id)->orderBy('name')->get()
            : collect();

        $modalDesas = $this->new_kecamatan_id
            ? Desa::where('kecamatan_id', $this->new_kecamatan_id)->orderBy('name')->get()
            : collect();

        $editModalDesas = $this->edit_kecamatan_id
            ? Desa::where('kecamatan_id', $this->edit_kecamatan_id)->orderBy('name')->get()
            : collect();

        $detailPus = null;
        if ($this->showDetailModal && $this->detail_pus_id) {
            $detailPus = PusProfile::forCurrentUser()
                ->with([
                    'kecamatan',
                    'desa',
                    'user',
                    'screenings' => fn ($q) => $q->with('interventions.user')->orderByDesc('tanggal_skrining'),
                ])
                ->find($this->detail_pus_id);
        }

        $isAdmin = ! $user || $user->isAdminDinas();

        return compact(
            'isAdmin',
            'pusList',
            'totalPus',
            'totalMupar',
            'sudahSkrining',
            'belumSkrining',
            'kecamatans',
            'desas',
            'modalDesas',
            'editModalDesas',
            'detailPus',
            'calculator'
        );
    }
};
?>

<div>
    <!-- Page Header & Actions -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </span>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                    Data Profil Pasangan Usia Subur (PUS)
                </h1>
            </div>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Basis data PUS Kabupaten Bangka, identifikasi otomatis status MUPAR, dan jalur cepat skrining 5 indikator.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/screening/create" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-[#008060] bg-white border border-[#008060]/30 hover:bg-[#008060]/5 dark:bg-slate-800 dark:text-emerald-400 dark:border-emerald-500/30 shadow-xs transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                Kalkulator Skrining
            </a>

            <button wire:click="openCreateModal"
                    type="button"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/20 transition transform active:scale-98">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Tambah Profil PUS
            </button>
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

    <!-- 4 TailAdmin Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- Total PUS -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-slate-500 dark:text-slate-400 uppercase">
                        Total PUS Terdata
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ number_format($totalPus) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-600 dark:text-slate-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span class="inline-block w-2 h-2 rounded-full bg-slate-400"></span>
                <span>Basis sasaran wilayah binaan</span>
            </div>
        </div>

        <!-- Sasaran MUPAR -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-900/60 shadow-xs ring-1 ring-purple-400/20">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-purple-700 dark:text-purple-300 uppercase">
                        Sasaran MUPAR
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-purple-950 dark:text-purple-100">
                        {{ number_format($totalMupar) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-purple-100 dark:bg-purple-950/60 flex items-center justify-center text-purple-700 dark:text-purple-300">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs font-medium text-purple-700 dark:text-purple-300">
                <span class="inline-block w-2 h-2 rounded-full bg-purple-600 animate-pulse"></span>
                <span>Usia ≤ 35 th &amp; Anak ≤ 2 (Prioritas Khusus)</span>
            </div>
        </div>

        <!-- Sudah Diskrining -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-emerald-200 dark:border-emerald-900/60 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-[#008060] dark:text-emerald-400 uppercase">
                        Sudah Diskrining
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ number_format($sudahSkrining) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-[#008060] dark:text-emerald-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span>Cakupan:</span>
                <span class="font-bold text-[#008060] dark:text-emerald-400">
                    {{ $totalPus > 0 ? round(($sudahSkrining / $totalPus) * 100, 1) : 0 }}%
                </span>
            </div>
        </div>

        <!-- Belum Diskrining -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-amber-200 dark:border-amber-900/60 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold tracking-wider text-amber-700 dark:text-amber-400 uppercase">
                        Belum Diskrining
                    </p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">
                        {{ number_format($belumSkrining) }}
                    </h3>
                </div>
                <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-3 flex items-center gap-1.5 text-xs text-amber-700 dark:text-amber-400 font-medium">
                <span class="inline-block w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Menunggu wawancara lapangan</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar (TailAdmin Table Three Style) -->
    <div class="mb-6 p-4 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <!-- Search -->
            <div class="lg:col-span-2 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Cari nama istri, suami, alamat..."
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
            </div>

            <!-- Filter Kecamatan -->
            <div>
                <select wire:model.live="kecamatan_id"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatans as $kec)
                        <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter Desa -->
            <div>
                <select wire:model.live="desa_id"
                        class="w-full px-3 py-2 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] focus:border-transparent transition"
                        {{ empty($desas) || $desas->isEmpty() ? 'disabled' : '' }}>
                    <option value="">Semua Desa / Kelurahan</option>
                    @foreach($desas as $desa)
                        <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Filter MUPAR & Status -->
            <div class="flex items-center gap-2">
                <select wire:model.live="filter_mupar"
                        class="w-1/2 px-2.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] transition">
                    <option value="">Semua MUPAR</option>
                    <option value="mupar">Ya (MUPAR)</option>
                    <option value="non_mupar">Bukan MUPAR</option>
                </select>

                <select wire:model.live="filter_skrining"
                        class="w-1/2 px-2.5 py-2 text-xs rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 dark:text-white focus:outline-hidden focus:ring-2 focus:ring-[#008060] transition">
                    <option value="">Status Skrining</option>
                    <option value="sudah">Sudah</option>
                    <option value="belum">Belum</option>
                </select>
            </div>
        </div>

        @if($search || $kecamatan_id || $desa_id || $filter_mupar || $filter_skrining)
            <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs">
                <span class="text-slate-500 dark:text-slate-400">
                    Filter aktif diterapkan
                </span>
                <button wire:click="resetFilters" type="button" class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline">
                    Reset Semua Filter
                </button>
            </div>
        @endif
    </div>

    <!-- Table Three TailAdmin -->
    <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="px-5 py-4">Pasangan PUS</th>
                        <th class="px-4 py-4">Usia Istri &amp; Flag MUPAR</th>
                        <th class="px-4 py-4">Paritas (Anak Hidup)</th>
                        <th class="px-4 py-4">Wilayah Binaan</th>
                        <th class="px-4 py-4">Skrining 5 Indikator</th>
                        <th class="px-5 py-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                    @forelse($pusList as $pus)
                        @php
                            $usia = $pus->usiaIstri();
                            $isMupar = $calculator->determineMupar($usia, $pus->jumlah_anak_lahir_hidup);
                            $latestScreening = $pus->latestScreening;
                        @endphp
                        <tr wire:key="pus-{{ $pus->id }}" class="hover:bg-slate-50/60 dark:hover:bg-slate-750 transition">
                            <!-- Pasangan PUS -->
                            <td class="px-5 py-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    Ny. {{ $pus->nama_istri }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    Tn. {{ $pus->nama_suami }}
                                </div>
                                @if($pus->rt_rw)
                                    <span class="inline-block mt-1 text-[11px] px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">
                                        {{ $pus->rt_rw }}
                                    </span>
                                @endif
                            </td>

                            <!-- Usia & MUPAR -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 dark:text-slate-200">
                                        {{ $usia }} tahun
                                    </span>
                                </div>
                                <div class="mt-1">
                                    @if($isMupar)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-purple-100 text-purple-800 border border-purple-300 dark:bg-purple-950/70 dark:text-purple-300 dark:border-purple-800">
                                            <svg class="w-3 h-3 text-purple-600 dark:text-purple-300" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                            </svg>
                                            MUPAR
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                                            Non-MUPAR
                                        </span>
                                    @endif
                                </div>
                            </td>

                            <!-- Paritas -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="font-medium text-slate-800 dark:text-slate-200">
                                    {{ $pus->jumlah_anak_lahir_hidup }} Anak
                                </span>
                                <div class="text-[11px] text-slate-400">
                                    Skor P2: {{ $calculator->calculateP2($pus->jumlah_anak_lahir_hidup) }}/3
                                </div>
                            </td>

                            <!-- Wilayah -->
                            <td class="px-4 py-4">
                                <div class="font-medium text-slate-900 dark:text-white">
                                    {{ $pus->desa?->name ?? '-' }}
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    Kec. {{ $pus->kecamatan?->name ?? '-' }}
                                </div>
                            </td>

                            <!-- Status Skrining Terakhir -->
                            <td class="px-4 py-4">
                                @if($latestScreening)
                                    @php
                                        $cat = $latestScreening->kategori;
                                        $badgeClass = match($cat) {
                                            \App\Enums\PriorityCategory::TINGGI => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/60 dark:text-red-300 dark:border-red-800',
                                            \App\Enums\PriorityCategory::SEDANG => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
                                            default => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                                        };
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 dark:text-white">
                                            Skor: {{ $latestScreening->total_skor }}/15
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $badgeClass }}">
                                            {{ $latestScreening->prioritas_akhir }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $latestScreening->tanggal_skrining->format('d M Y') }}
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300">
                                        Belum Diskrining
                                    </span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Detail / Riwayat Button -->
                                    <button wire:click="openDetailModal({{ $pus->id }})"
                                            type="button"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 dark:text-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 transition"
                                            title="Lihat Detail & Riwayat Skrining">
                                        <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        <span>Detail</span>
                                    </button>

                                    <!-- Skrining Button -->
                                    <a href="/screening/create?pus_id={{ $pus->id }}" wire:navigate
                                       class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-xs transition"
                                       title="Mulai skrining 5 indikator">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                        </svg>
                                        <span>Skrining</span>
                                    </a>

                                    <!-- Edit Button -->
                                    <button wire:click="openEditModal({{ $pus->id }})"
                                            type="button"
                                            class="p-1.5 text-slate-500 hover:text-[#008060] dark:text-slate-400 dark:hover:text-emerald-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                            title="Edit Profil PUS">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>

                                    <!-- Delete Button -->
                                    <button wire:click="deletePus({{ $pus->id }})"
                                            wire:confirm="Yakin ingin menghapus profil PUS Ny. {{ $pus->nama_istri }}?"
                                            type="button"
                                            class="p-1.5 text-slate-400 hover:text-red-600 dark:hover:text-red-400 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                            title="Hapus data">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="max-w-sm mx-auto text-center">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-700/60 flex items-center justify-center text-slate-400">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                        </svg>
                                    </div>
                                    <h4 class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">
                                        Tidak ada data PUS ditemukan
                                    </h4>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Coba sesuaikan kata kunci pencarian atau daftarkan profil PUS baru.
                                    </p>
                                    <button wire:click="openCreateModal"
                                            type="button"
                                            class="mt-4 inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52]">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                        Tambah Profil PUS Baru
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($pusList->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $pusList->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Form Tambah PUS -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-999999 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
            <div class="w-full max-w-2xl bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden transform transition-all mx-auto" style="max-width: 640px;">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/60 flex items-center justify-between bg-slate-50/60 dark:bg-slate-900/40">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-900 dark:text-white">
                                Tambah Data Profil PUS
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Daftarkan pasangan usia subur baru ke sistem SI-MENTILIN
                            </p>
                        </div>
                    </div>

                    <button wire:click="$set('showCreateModal', false)"
                            type="button"
                            class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form wire:submit="savePus">
                    <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                        
                        <!-- Nama Istri & Suami -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Nama Istri <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="nama_istri"
                                       type="text"
                                       placeholder="Contoh: Siti Aisyah"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('nama_istri') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Nama Suami <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="nama_suami"
                                       type="text"
                                       placeholder="Contoh: Ahmad Fauzi"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('nama_suami') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Tanggal Lahir Istri & Jumlah Anak -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Tanggal Lahir Istri <span class="text-red-500">*</span>
                                </label>
                                <input wire:model.live="tanggal_lahir_istri"
                                       type="date"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('tanggal_lahir_istri') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Jumlah Anak Lahir Hidup <span class="text-red-500">*</span>
                                </label>
                                <input wire:model.live="jumlah_anak_lahir_hidup"
                                       type="number"
                                       min="0"
                                       max="20"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('jumlah_anak_lahir_hidup') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Live MUPAR Preview Box -->
                        @if($tanggal_lahir_istri)
                            @php
                                $birthDate = rescue(fn () => \Illuminate\Support\Carbon::parse($tanggal_lahir_istri), null);
                                $age = $birthDate ? (int) $birthDate->diffInYears(now()) : 0;
                                $isModalMupar = $calculator->determineMupar($age, (int) $jumlah_anak_lahir_hidup);
                            @endphp
                            <div class="p-3.5 rounded-xl border {{ $isModalMupar ? 'bg-purple-50/80 border-purple-200 text-purple-900 dark:bg-purple-950/40 dark:border-purple-800' : 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300' }} flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">Estimasi Usia Istri:</span>
                                    <span>{{ $age }} tahun</span>
                                    <span>&bull;</span>
                                    <span class="font-bold">Skor P1:</span>
                                    <span>{{ $calculator->calculateP1($age) }}/3</span>
                                    <span>&bull;</span>
                                    <span class="font-bold">Skor P2:</span>
                                    <span>{{ $calculator->calculateP2((int) $jumlah_anak_lahir_hidup) }}/3</span>
                                </div>
                                <div>
                                    @if($isModalMupar)
                                        <span class="px-2 py-0.5 rounded-md font-bold bg-purple-200 text-purple-900 dark:bg-purple-900 dark:text-purple-200">
                                            Status: MUPAR
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-slate-600 dark:text-slate-400">
                                            Status: Non-MUPAR
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Wilayah Kecamatan & Desa -->
                        @if(! $isAdmin && auth()->user()?->desa_id)
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs">
                                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    <span class="font-bold">Wilayah Penugasan:</span>
                                    <span>{{ auth()->user()->kecamatan?->name ?? '-' }} &bull; {{ auth()->user()->desa?->name ?? '-' }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">PUS otomatis terdaftar pada desa penugasan Anda.</p>
                            </div>
                        @elseif(! $isAdmin)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs">
                                    <span class="font-bold text-slate-700 dark:text-slate-300">Kecamatan Penugasan:</span>
                                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400 mt-0.5">{{ auth()->user()->kecamatan?->name ?? '-' }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                        Desa / Kelurahan <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="new_desa_id"
                                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                                        <option value="">Pilih Desa di Kecamatan Anda</option>
                                        @foreach($modalDesas as $desa)
                                            <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_desa_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                        Kecamatan <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model.live="new_kecamatan_id"
                                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                                        <option value="">Pilih Kecamatan</option>
                                        @foreach($kecamatans as $kec)
                                            <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_kecamatan_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                        Desa / Kelurahan <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="new_desa_id"
                                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                                        <option value="">Pilih Desa / Kelurahan</option>
                                        @foreach($modalDesas as $desa)
                                            <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('new_desa_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- RT / RW & Alamat -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    RT / RW
                                </label>
                                <input wire:model="rt_rw"
                                       type="text"
                                       placeholder="RT 02 / RW 01"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Alamat Domisili
                                </label>
                                <input wire:model="alamat"
                                       type="text"
                                       placeholder="Jl. Raya Belinyu No. 12"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="px-6 py-4 bg-slate-50/60 dark:bg-slate-900/40 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-end gap-3">
                        <button wire:click="$set('showCreateModal', false)"
                                type="button"
                                class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition">
                            Batal
                        </button>

                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/20 transition data-loading:opacity-50 data-loading:pointer-events-none">
                            <span wire:loading.remove wire:target="savePus">Simpan Profil PUS</span>
                            <span wire:loading wire:target="savePus">Menyimpan...</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL EDIT PROFIL PUS (Z-Index Tinggi, Tepat Di Tengah)
         ════════════════════════════════════════════════════════════ -->
    @if($showEditModal)
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-[999999]"
             x-data @keydown.escape.window="$wire.set('showEditModal', false)">
            <div class="w-full max-w-xl bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-700 overflow-hidden transform transition-all my-auto max-h-[90vh] flex flex-col">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-slate-900 to-[#0B2E59] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-white/10 text-emerald-400 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white leading-tight">Edit Data Profil PUS</h3>
                            <p class="text-xs text-slate-300">Perbarui identitas, domisili, atau paritas sasaran</p>
                        </div>
                    </div>
                    <button wire:click="$set('showEditModal', false)"
                            type="button"
                            class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Modal Body -->
                <form wire:submit="updatePus" class="overflow-y-auto p-6 space-y-4 flex-1">
                    <div class="space-y-4">
                        
                        <!-- Nama Istri & Nama Suami -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Nama Istri <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="edit_nama_istri"
                                       type="text"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('edit_nama_istri') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Nama Suami <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="edit_nama_suami"
                                       type="text"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('edit_nama_suami') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Tanggal Lahir Istri & Jumlah Anak -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Tanggal Lahir Istri <span class="text-red-500">*</span>
                                </label>
                                <input wire:model.live="edit_tanggal_lahir_istri"
                                       type="date"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('edit_tanggal_lahir_istri') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Jumlah Anak Lahir Hidup <span class="text-red-500">*</span>
                                </label>
                                <input wire:model.live="edit_jumlah_anak_lahir_hidup"
                                       type="number"
                                       min="0"
                                       max="20"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                @error('edit_jumlah_anak_lahir_hidup') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <!-- Live MUPAR Preview Box -->
                        @if($edit_tanggal_lahir_istri)
                            @php
                                $editBirthDate = rescue(fn () => \Illuminate\Support\Carbon::parse($edit_tanggal_lahir_istri), null);
                                $editAge = $editBirthDate ? (int) $editBirthDate->diffInYears(now()) : 0;
                                $isEditMupar = $calculator->determineMupar($editAge, (int) $edit_jumlah_anak_lahir_hidup);
                            @endphp
                            <div class="p-3.5 rounded-xl border {{ $isEditMupar ? 'bg-purple-50/80 border-purple-200 text-purple-900 dark:bg-purple-950/40 dark:border-purple-800' : 'bg-slate-50 border-slate-200 text-slate-700 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300' }} flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold">Usia Istri:</span>
                                    <span>{{ $editAge }} tahun</span>
                                    <span>&bull;</span>
                                    <span class="font-bold">Skor P1:</span>
                                    <span>{{ $calculator->calculateP1($editAge) }}/3</span>
                                    <span>&bull;</span>
                                    <span class="font-bold">Skor P2:</span>
                                    <span>{{ $calculator->calculateP2((int) $edit_jumlah_anak_lahir_hidup) }}/3</span>
                                </div>
                                <div>
                                    @if($isEditMupar)
                                        <span class="px-2 py-0.5 rounded-md font-bold bg-purple-200 text-purple-900 dark:bg-purple-900 dark:text-purple-200">
                                            Status: MUPAR
                                        </span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-slate-600 dark:text-slate-400">
                                            Status: Non-MUPAR
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- Wilayah Kecamatan & Desa -->
                        @if(! $isAdmin)
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 text-xs">
                                <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300">
                                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                    <span class="font-bold">Wilayah Terdaftar:</span>
                                    <span>{{ $kecamatans->firstWhere('id', $edit_kecamatan_id)?->name ?? '-' }} &bull; {{ $editModalDesas->firstWhere('id', $edit_desa_id)?->name ?? '-' }}</span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Wilayah domisili dikunci sesuai penugasan petugas lapangan dan tidak dapat dipindahkan.</p>
                            </div>
                        @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                        Kecamatan <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model.live="edit_kecamatan_id"
                                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                                        <option value="">Pilih Kecamatan</option>
                                        @foreach($kecamatans as $kec)
                                            <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('edit_kecamatan_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                        Desa / Kelurahan <span class="text-red-500">*</span>
                                    </label>
                                    <select wire:model="edit_desa_id"
                                            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                                        <option value="">Pilih Desa / Kelurahan</option>
                                        @foreach($editModalDesas as $desa)
                                            <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('edit_desa_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- RT / RW & Alamat -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    RT / RW
                                </label>
                                <input wire:model="edit_rt_rw"
                                       type="text"
                                       placeholder="RT 02 / RW 01"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                            </div>

                            <div class="sm:col-span-2">
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                                    Alamat Domisili
                                </label>
                                <input wire:model="edit_alamat"
                                       type="text"
                                       placeholder="Jl. Raya Belinyu No. 12"
                                       class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                            </div>
                        </div>

                    </div>

                    <!-- Modal Footer Actions -->
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-end gap-3">
                        <button wire:click="$set('showEditModal', false)"
                                type="button"
                                class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200/60 dark:hover:bg-slate-700 transition">
                            Batal
                        </button>

                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/20 transition data-loading:opacity-50 data-loading:pointer-events-none">
                            <span wire:loading.remove wire:target="updatePus">Perbarui Data</span>
                            <span wire:loading wire:target="updatePus">Menyimpan...</span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL DETAIL & TIMELINE RIWAYAT PUS
         ════════════════════════════════════════════════════════════ -->
    @if($showDetailModal && $detailPus)
        @php
            $detailAge = $detailPus->usiaIstri();
            $isDetailMupar = $calculator->determineMupar($detailAge, $detailPus->jumlah_anak_lahir_hidup);
        @endphp
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 z-[999999]"
             x-data @keydown.escape.window="$wire.closeDetailModal()">
            <div class="w-full max-w-3xl bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border border-slate-100 dark:border-slate-700 overflow-hidden transform transition-all my-auto max-h-[90vh] flex flex-col">
                
                <!-- Detail Header -->
                <div class="px-6 py-5 bg-gradient-to-r from-[#0B2E59] via-[#0e3a6e] to-[#008060] text-white flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/10 text-emerald-300 flex items-center justify-center font-bold text-sm">
                            {{ substr($detailPus->nama_istri, 0, 1) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-white leading-tight">
                                    Ny. {{ $detailPus->nama_istri }} &amp; Tn. {{ $detailPus->nama_suami }}
                                </h3>
                                @if($isDetailMupar)
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-500/30 text-purple-200 border border-purple-400/30">
                                        MUPAR
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-200 mt-0.5">
                                Desa/Kel. {{ $detailPus->desa?->name ?? '-' }}, Kec. {{ $detailPus->kecamatan?->name ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="/screening/create?pus_id={{ $detailPus->id }}" wire:navigate
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold shadow transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Skrining Baru</span>
                        </a>

                        <button wire:click="closeDetailModal"
                                type="button"
                                class="p-1.5 rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Profile Summary Quick Info Bar -->
                <div class="px-6 py-3 bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700/60 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs shrink-0">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Usia Istri</span>
                        <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $detailAge }} Tahun</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Jumlah Anak</span>
                        <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5">{{ $detailPus->jumlah_anak_lahir_hidup }} Orang</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Alamat Domisili</span>
                        <p class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 truncate">{{ $detailPus->alamat ?: ($detailPus->rt_rw ?: '-') }}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Total Skrining</span>
                        <p class="font-bold text-[#008060] dark:text-emerald-400 mt-0.5">{{ $detailPus->screenings->count() }} Kali</p>
                    </div>
                </div>

                <!-- Timeline Riwayat Skrining & Intervensi -->
                <div class="overflow-y-auto p-6 space-y-6 flex-1">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Timeline Rekam Jejak Skrining &amp; Intervensi KB</span>
                    </h4>

                    @if($detailPus->screenings->isEmpty())
                        <div class="py-12 text-center rounded-2xl bg-slate-50 dark:bg-slate-900/40 border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 flex items-center justify-center mb-3">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <p class="text-sm font-bold text-slate-700 dark:text-slate-300">Belum Ada Rekam Skrining</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-sm mx-auto">
                                Pasangan Usia Subur ini belum pernah dilakukan skrining 5 indikator.
                            </p>
                            <a href="/screening/create?pus_id={{ $detailPus->id }}" wire:navigate
                               class="inline-flex items-center gap-1.5 mt-4 px-4 py-2 rounded-xl bg-[#008060] text-white text-xs font-bold shadow hover:bg-[#006e52] transition">
                                Mulai Skrining Pertama
                            </a>
                        </div>
                    @else
                        <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-700">
                            @foreach($detailPus->screenings as $sc)
                                @php
                                    $scBadge = match($sc->kategori) {
                                        \App\Enums\PriorityCategory::TINGGI => 'bg-red-100 text-red-800 border-red-300 dark:bg-red-950/60 dark:text-red-300',
                                        \App\Enums\PriorityCategory::SEDANG => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300',
                                        default => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300',
                                    };
                                @endphp
                                <div class="relative">
                                    <!-- Node marker -->
                                    <div class="absolute -left-6 top-1.5 w-5 h-5 rounded-full border-2 border-white dark:border-slate-800 {{ $sc->total_skor >= 11 ? 'bg-purple-600' : ($sc->total_skor >= 7 ? 'bg-amber-500' : 'bg-emerald-500') }}"></div>

                                    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 shadow-xs space-y-3">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-extrabold text-slate-900 dark:text-white">
                                                    {{ $sc->tanggal_skrining->translatedFormat('d F Y') }}
                                                </span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border {{ $scBadge }}">
                                                    {{ $sc->prioritas_akhir }}
                                                </span>
                                                @if($sc->is_mupar)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-purple-100 text-purple-800 dark:bg-purple-950/70 dark:text-purple-300">
                                                        MUPAR
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                                Total Skor: <span class="text-base font-black {{ $sc->total_skor >= 11 ? 'text-purple-600 dark:text-purple-400' : 'text-[#008060] dark:text-emerald-400' }}">{{ $sc->total_skor }}</span> / 15
                                            </div>
                                        </div>

                                        <!-- Rincian 5 Parameter -->
                                        <div class="grid grid-cols-5 gap-2 text-center p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 text-[11px]">
                                            <div>
                                                <span class="block text-[9px] text-slate-400 uppercase font-bold">P1: Usia</span>
                                                <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $sc->p1_usia_istri }}/3</span>
                                            </div>
                                            <div>
                                                <span class="block text-[9px] text-slate-400 uppercase font-bold">P2: Paritas</span>
                                                <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $sc->p2_jumlah_anak }}/3</span>
                                            </div>
                                            <div>
                                                <span class="block text-[9px] text-slate-400 uppercase font-bold">P3: Status KB</span>
                                                <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $sc->p3_status_kontrasepsi }}/3</span>
                                            </div>
                                            <div>
                                                <span class="block text-[9px] text-slate-400 uppercase font-bold">P4: Tujuan</span>
                                                <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $sc->p4_tujuan_reproduksi }}/3</span>
                                            </div>
                                            <div>
                                                <span class="block text-[9px] text-slate-400 uppercase font-bold">P5: Risiko</span>
                                                <span class="font-extrabold text-slate-700 dark:text-slate-300">{{ $sc->p5_risiko_kehamilan }}/3</span>
                                            </div>
                                        </div>

                                        @if($sc->catatan)
                                            <p class="text-xs text-slate-500 italic bg-amber-50/50 dark:bg-amber-950/20 p-2 rounded-lg border border-amber-200/50">
                                                "{{ $sc->catatan }}"
                                            </p>
                                        @endif

                                        <!-- Log Intervensi yang Terhubung -->
                                        @if($sc->interventions->isNotEmpty())
                                            <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                                                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block mb-1.5">
                                                    Tindak Lanjut Intervensi Lapangan:
                                                </span>
                                                @foreach($sc->interventions as $it)
                                                    <div class="p-2.5 rounded-xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-900/40 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                                        <div>
                                                            <div class="flex items-center gap-1.5 font-bold text-slate-800 dark:text-slate-200">
                                                                <span>{{ $it->jenis_tindakan }}</span>
                                                                @if($it->metode_kb)
                                                                    <span class="px-2 py-0.5 rounded-full text-[10px] bg-[#008060] text-white">
                                                                        {{ $it->metode_kb }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <p class="text-[11px] text-slate-500 mt-0.5">
                                                                Oleh: {{ $it->user?->name ?? 'Petugas PKB' }} &bull; Tanggal: {{ $it->tanggal_kunjungan->translatedFormat('d M Y') }}
                                                            </p>
                                                        </div>
                                                        <div class="text-right text-[11px] font-mono text-slate-600 dark:text-slate-400">
                                                            <div>{{ $it->durasi_menit }} Menit Konseling</div>
                                                            <div class="font-bold text-emerald-700 dark:text-emerald-300">Rp {{ number_format($it->biaya_operasional, 0, ',', '.') }}</div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <div class="pt-1 flex items-center justify-between text-xs text-slate-400">
                                                <span>Belum ada tindakan intervensi yang dicatat</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300">
                                                    Menunggu Kunjungan
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Detail Footer -->
                <div class="px-6 py-4 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-slate-700/60 flex items-center justify-between shrink-0">
                    <span class="text-xs text-slate-400">
                        ID Sasaran: #PUS-{{ str_pad($detailPus->id, 5, '0', STR_PAD_LEFT) }}
                    </span>
                    <button wire:click="closeDetailModal"
                            type="button"
                            class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 bg-slate-200/80 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 transition">
                        Tutup
                    </button>
                </div>

            </div>
        </div>
    @endif
</div>