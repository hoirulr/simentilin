<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Pengguna | SI-MENTILIN')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $filter_role = '';

    #[Url(as: 'kec')]
    public ?int $filter_kecamatan_id = null;

    // Modal Visibility States
    public bool $showCreateModal = false;
    public bool $showEditModal = false;
    public bool $showResetPasswordModal = false;
    public bool $showDeleteModal = false;

    // Form inputs for Create / Edit
    public ?int $editingUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'petugas_lapangan';
    public ?int $kecamatan_id = null;
    public ?int $desa_id = null;

    // Form inputs for Reset Password
    public ?int $targetUserId = null;
    public string $targetUserName = '';
    public string $new_password = '';
    public string $new_password_confirmation = '';

    // Deleting state
    public ?int $deletingUserId = null;
    public string $deletingUserName = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatedFilterKecamatanId(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter_role = '';
        $this->filter_kecamatan_id = null;
        $this->resetPage();
    }

    public function updatedRole(): void
    {
        if ($this->role === UserRole::ADMIN_DINAS->value) {
            $this->kecamatan_id = null;
            $this->desa_id = null;
        }
    }

    public function updatedKecamatanId(): void
    {
        $this->desa_id = null;
    }

    // ──────────────── CREATE USER ────────────────
    public function openCreateModal(): void
    {
        $this->authorize('create', User::class);
        $this->resetValidation();
        $this->editingUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->role = UserRole::PETUGAS_LAPANGAN->value;
        $this->kecamatan_id = Kecamatan::first()?->id;
        $this->desa_id = null;

        $this->showCreateModal = true;
    }

    public function createUser(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::enum(UserRole::class)],
            'kecamatan_id' => [
                Rule::requiredIf($this->role === UserRole::PETUGAS_LAPANGAN->value),
                'nullable',
                'exists:kecamatans,id',
            ],
            'desa_id' => [
                'nullable',
                Rule::exists('desas', 'id')->where('kecamatan_id', $this->kecamatan_id),
            ],
        ], [
            'name.required' => 'Nama lengkap pengguna wajib diisi.',
            'email.required' => 'Alamat email dinas wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar di sistem.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'kecamatan_id.required_if' => 'Wilayah kecamatan penugasan wajib dipilih untuk Petugas Lapangan.',
            'desa_id.exists' => 'Desa tidak valid atau bukan bagian dari kecamatan yang dipilih.',
        ]);

        $isPetugas = $validated['role'] === UserRole::PETUGAS_LAPANGAN->value || $validated['role'] === UserRole::PETUGAS_LAPANGAN;

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'is_active' => true,
            'kecamatan_id' => $isPetugas ? $validated['kecamatan_id'] : null,
            'desa_id' => $isPetugas ? $validated['desa_id'] : null,
        ]);

        AuditLog::log(
            action: 'create_user',
            summary: "Admin membuat akun pengguna baru: {$newUser->name} ({$newUser->email})",
            auditable: $newUser,
            newValues: ['name' => $newUser->name, 'email' => $newUser->email, 'role' => $newUser->role->value]
        );

        $this->showCreateModal = false;
        session()->flash('success', "Akun pengguna '{$validated['name']}' berhasil ditambahkan ke dalam sistem!");
    }

    // ──────────────── EDIT USER ────────────────
    public function openEditModal(int $userId): void
    {
        $this->resetValidation();
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->kecamatan_id = $user->kecamatan_id;
        $this->desa_id = $user->desa_id;

        $this->showEditModal = true;
    }

    public function updateUser(): void
    {
        $user = User::findOrFail($this->editingUserId);
        $this->authorize('update', $user);

        $validated = $this->validate([
            'name' => 'required|string|max:150',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->editingUserId)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'kecamatan_id' => [
                Rule::requiredIf($this->role === UserRole::PETUGAS_LAPANGAN->value),
                'nullable',
                'exists:kecamatans,id',
            ],
            'desa_id' => [
                'nullable',
                Rule::exists('desas', 'id')->where('kecamatan_id', $this->kecamatan_id),
            ],
        ], [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.required' => 'Email dinas wajib diisi.',
            'email.unique' => 'Email ini telah digunakan oleh pengguna lain.',
            'kecamatan_id.required_if' => 'Kecamatan wajib dipilih untuk Petugas Lapangan.',
            'desa_id.exists' => 'Desa tidak valid atau bukan bagian dari kecamatan yang dipilih.',
        ]);

        $isPetugas = $validated['role'] === UserRole::PETUGAS_LAPANGAN->value || $validated['role'] === UserRole::PETUGAS_LAPANGAN;

        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'kecamatan_id' => $user->kecamatan_id,
            'desa_id' => $user->desa_id,
        ];

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'kecamatan_id' => $isPetugas ? $validated['kecamatan_id'] : null,
            'desa_id' => $isPetugas ? $validated['desa_id'] : null,
        ]);

        AuditLog::log(
            action: 'update_user',
            summary: "Admin memperbarui profil pengguna: {$user->name} (ID: {$user->id})",
            auditable: $user,
            oldValues: $oldValues,
            newValues: ['name' => $user->name, 'email' => $user->email, 'role' => $user->role->value]
        );

        $this->showEditModal = false;
        session()->flash('success', "Data pengguna '{$user->name}' berhasil diperbarui.");
    }

    // ──────────────── RESET PASSWORD ────────────────
    public function openResetPasswordModal(int $userId): void
    {
        $this->resetValidation();
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        $this->targetUserId = $user->id;
        $this->targetUserName = $user->name;
        $this->new_password = '';
        $this->new_password_confirmation = '';

        $this->showResetPasswordModal = true;
    }

    public function resetPassword(): void
    {
        $user = User::findOrFail($this->targetUserId);
        $this->authorize('update', $user);

        $this->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ], [
            'new_password.required' => 'Kata sandi baru wajib diisi.',
            'new_password.min' => 'Kata sandi baru minimal 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($this->new_password),
        ]);

        AuditLog::log(
            action: 'reset_password',
            summary: "Admin mengatur ulang kata sandi pengguna: {$user->name} (ID: {$user->id})",
            auditable: $user
        );

        $this->showResetPasswordModal = false;
        $this->new_password = '';
        $this->new_password_confirmation = '';
        session()->flash('success', "Kata sandi untuk pengguna '{$user->name}' berhasil diatur ulang.");
    }

    // ──────────────── DELETE USER ────────────────
    public function confirmDelete(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('delete', $user);

        $this->deletingUserId = $user->id;
        $this->deletingUserName = $user->name;
        $this->showDeleteModal = true;
    }

    public function deleteUser(): void
    {
        $user = User::withCount(['pusProfiles', 'screenings', 'interventions'])->findOrFail($this->deletingUserId);
        $this->authorize('delete', $user);

        if ($user->pus_profiles_count > 0 || $user->screenings_count > 0 || $user->interventions_count > 0) {
            session()->flash('error', "Pengguna '{$user->name}' tidak dapat dihapus karena memiliki data historis ({$user->pus_profiles_count} profil PUS, {$user->screenings_count} skrining, {$user->interventions_count} intervensi). Silakan nonaktifkan akun sebagai gantinya.");
            $this->showDeleteModal = false;
            return;
        }

        $userName = $user->name;
        $user->delete();

        AuditLog::log(
            action: 'delete_user',
            summary: "Admin menghapus akun pengguna: {$userName} (ID: {$this->deletingUserId})",
            auditable: $user
        );

        $this->showDeleteModal = false;
        session()->flash('success', "Akun pengguna '{$userName}' berhasil dihapus dari sistem.");
    }

    public function toggleUserStatus(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('update', $user);

        if ($user->id === auth()->id()) {
            session()->flash('error', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
            return;
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::log(
            action: 'toggle_user_status',
            summary: "Admin mengubah status akun {$user->name} menjadi {$statusStr}",
            auditable: $user
        );

        session()->flash('success', "Akun '{$user->name}' berhasil {$statusStr}.");
    }

    public function with(): array
    {
        $query = User::query()->with(['kecamatan', 'desa'])->withCount(['pusProfiles', 'screenings', 'interventions']);

        // Search Filter
        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        // Role Filter
        if ($this->filter_role !== '') {
            $query->where('role', $this->filter_role);
        }

        // Kecamatan Filter
        if ($this->filter_kecamatan_id) {
            $query->where('kecamatan_id', $this->filter_kecamatan_id);
        }

        $users = $query->orderBy('role', 'asc')->orderBy('name', 'asc')->paginate(10);

        // Statistics
        $totalUsers = User::count();
        $totalPetugas = User::where('role', UserRole::PETUGAS_LAPANGAN)->count();
        $totalAdmin = User::where('role', UserRole::ADMIN_DINAS)->count();
        $totalPusManaged = \App\Models\PusProfile::count();

        // Territory dropdown data
        $kecamatans = Kecamatan::orderBy('name')->get();
        $formDesas = $this->kecamatan_id
            ? Desa::where('kecamatan_id', $this->kecamatan_id)->orderBy('name')->get()
            : collect();

        return compact(
            'users',
            'totalUsers',
            'totalPetugas',
            'totalAdmin',
            'totalPusManaged',
            'kecamatans',
            'formDesas'
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
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    <span>Hak Akses Super Admin DP2KBP3A Kab. Bangka</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Manajemen Pengguna &amp; Penugasan Wilayah
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Kelola akun Petugas Lapangan (PKB/PLKB), Koordinator Kecamatan, dan Administrator Dinas. Atur hak akses serta penetapan wilayah binaan (Kecamatan &amp; Desa) se-Kabupaten Bangka.
                </p>
            </div>

            <!-- Add User Button -->
            <div>
                <button wire:click="openCreateModal"
                        type="button"
                        class="inline-flex items-center gap-2.5 px-5 py-3 rounded-2xl bg-gradient-to-r from-[#008060] to-[#10B981] hover:from-[#006e52] hover:to-[#059669] text-white font-bold text-sm shadow-xl shadow-black/20 transition transform active:scale-95 focus:outline-none ring-2 ring-white/30">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    <span>Tambah Pengguna Baru</span>
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
         STATISTIC CARDS (TailAdmin Data Stats)
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Users -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Akun</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalUsers) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-[#0B2E59] dark:text-blue-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                <span>Terdaftar dalam sistem SI-MENTILIN</span>
            </p>
        </div>

        <!-- Petugas Lapangan -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Petugas Lapangan (PKB)</p>
                    <h3 class="text-2xl font-black text-[#008060] dark:text-emerald-400 mt-1">{{ number_format($totalPetugas) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Pelaksana skrining &amp; intervensi 8 Kec.</span>
            </p>
        </div>

        <!-- Admin Dinas -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Admin DP2KBP3A</p>
                    <h3 class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-1">{{ number_format($totalAdmin) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>Akses penuh &amp; monitoring kabupaten</span>
            </p>
        </div>

        <!-- Total PUS Dikelola -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total PUS Terdata</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($totalPusManaged) }}</h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-300 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                <span>Sasaran aktif terdaftar</span>
            </p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         FILTER & SEARCH BAR
         ════════════════════════════════════════════════════════════ -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-center">
            
            <!-- Search Input -->
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Cari nama atau email..."
                       class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
            </div>

            <!-- Role Filter -->
            <div>
                <select wire:model.live="filter_role"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Peran (Role)</option>
                    <option value="{{ UserRole::PETUGAS_LAPANGAN->value }}">Petugas Lapangan (PKB/PLKB)</option>
                    <option value="{{ UserRole::ADMIN_DINAS->value }}">Admin Dinas (DP2KBP3A)</option>
                </select>
            </div>

            <!-- Kecamatan Filter -->
            <div>
                <select wire:model.live="filter_kecamatan_id"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    <option value="">Semua Kecamatan</option>
                    @foreach($kecamatans as $kec)
                        <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Reset Filters -->
            <div class="flex items-center gap-2">
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
         USER TABLE (TailAdmin Table Three)
         ════════════════════════════════════════════════════════════ -->
    <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                        <th class="py-4 px-6">Pengguna &amp; Kontak</th>
                        <th class="py-4 px-6">Peran (Role)</th>
                        <th class="py-4 px-6">Wilayah Penugasan</th>
                        <th class="py-4 px-6 text-center">Aktivitas Terdata</th>
                        <th class="py-4 px-6 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60 text-xs text-slate-700 dark:text-slate-300">
                    @forelse($users as $user)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-750/50 transition">
                            <!-- User & Contact Info -->
                            <td class="py-4 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm shadow-xs {{ $user->isAdminDinas() ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-amber-300 dark:ring-amber-700' : 'bg-emerald-100 text-[#008060] dark:bg-emerald-950/60 dark:text-emerald-300 ring-1 ring-emerald-300 dark:ring-emerald-700' }}">
                                        {{ $user->initials() }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-slate-900 dark:text-white text-sm leading-tight">
                                                {{ $user->name }}
                                            </p>
                                            @if($user->id === auth()->id())
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#008060]/10 text-[#008060] dark:bg-emerald-950 dark:text-emerald-300 border border-[#008060]/30">
                                                    Anda
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5">
                                            {{ $user->email }}
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <!-- Role Badge -->
                            <td class="py-4 px-6">
                                @if($user->isAdminDinas())
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-700/60">
                                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                                        <span>Admin Dinas (DP2KBP3A)</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/40 text-[#008060] dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700/60">
                                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                        <span>Petugas Lapangan (PKB)</span>
                                    </span>
                                @endif
                            </td>

                            <!-- Territory Info -->
                            <td class="py-4 px-6">
                                @if($user->isAdminDinas())
                                    <div class="flex items-center gap-1.5 text-slate-700 dark:text-slate-300 font-medium">
                                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                        </svg>
                                        <span>Seluruh Kabupaten Bangka (8 Kec)</span>
                                    </div>
                                @elseif($user->kecamatan)
                                    <div>
                                        <p class="font-semibold text-slate-900 dark:text-white flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            </svg>
                                            <span>Kec. {{ $user->kecamatan->name }}</span>
                                        </p>
                                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 ml-4.5">
                                            {{ $user->desa ? 'Desa/Kel. ' . $user->desa->name : 'Koordinator / Seluruh Desa' }}
                                        </p>
                                    </div>
                                @else
                                    <span class="text-slate-400 italic">Belum ditentukan</span>
                                @endif
                            </td>

                            <!-- Activity Counters -->
                            <td class="py-4 px-6 text-center">
                                @if($user->isPetugasLapangan())
                                    <div class="inline-flex items-center gap-3 px-3 py-1 rounded-xl bg-slate-100 dark:bg-slate-700/50 text-xs">
                                        <span title="Profil PUS Terdaftar" class="font-semibold text-slate-700 dark:text-slate-300">
                                            {{ $user->pus_profiles_count }} <span class="text-[10px] font-normal text-slate-400">PUS</span>
                                        </span>
                                        <span class="text-slate-300 dark:text-slate-600">|</span>
                                        <span title="Skrining Dilakukan" class="font-semibold text-[#008060] dark:text-emerald-400">
                                            {{ $user->screenings_count }} <span class="text-[10px] font-normal text-slate-400">Skrining</span>
                                        </span>
                                    </div>
                                @else
                                    <span class="text-slate-400 text-xs">Monitoring Pusat</span>
                                @endif
                            </td>

                            <!-- Actions -->
                            <td class="py-4 px-6 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <!-- Edit User Button -->
                                    <button wire:click="openEditModal({{ $user->id }})"
                                            type="button"
                                            class="p-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition"
                                            title="Edit Data Pengguna">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                    </button>

                                    <!-- Reset Password Button -->
                                    <button wire:click="openResetPasswordModal({{ $user->id }})"
                                            type="button"
                                            class="p-2 rounded-lg text-amber-600 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-950/40 transition"
                                            title="Atur Ulang Kata Sandi">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                        </svg>
                                    </button>

                                    <!-- Delete Button -->
                                    @if($user->id !== auth()->id())
                                        <button wire:click="confirmDelete({{ $user->id }})"
                                                type="button"
                                                class="p-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/40 transition"
                                                title="Hapus Akun Pengguna">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    @else
                                        <span class="p-2 text-slate-300 dark:text-slate-600 cursor-not-allowed" title="Tidak dapat menghapus akun sendiri">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            </svg>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <svg class="w-10 h-10 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <p class="font-semibold text-sm">Tidak ada data pengguna yang sesuai.</p>
                                    <p class="text-xs">Coba sesuaikan kata kunci pencarian atau reset filter.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        @if($users->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    <!-- ════════════════════════════════════════════════════════════
         MODAL 1: TAMBAH PENGGUNA BARU
         ════════════════════════════════════════════════════════════ -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showCreateModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 560px;">
                    
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-title">
                                Tambah Pengguna Baru
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Daftarkan akun Petugas Lapangan atau Administrator DP2KBP3A.
                            </p>
                        </div>
                        <button wire:click="$set('showCreateModal', false)"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Form -->
                    <form wire:submit="createUser" class="space-y-4 mt-5">
                        
                        <!-- Role Selection -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                Peran Akun (Role) <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="relative flex flex-col p-3.5 rounded-2xl border cursor-pointer transition {{ $role === UserRole::PETUGAS_LAPANGAN->value ? 'border-[#008060] bg-emerald-50/50 dark:bg-emerald-950/30 ring-2 ring-[#008060]/30' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">Petugas Lapangan</span>
                                        <input type="radio" wire:model.live="role" value="{{ UserRole::PETUGAS_LAPANGAN->value }}" class="text-[#008060] focus:ring-[#008060]">
                                    </div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">PKB / PLKB &amp; Koordinator Kecamatan</span>
                                </label>

                                <label class="relative flex flex-col p-3.5 rounded-2xl border cursor-pointer transition {{ $role === UserRole::ADMIN_DINAS->value ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/30 ring-2 ring-amber-500/30' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">Admin Dinas</span>
                                        <input type="radio" wire:model.live="role" value="{{ UserRole::ADMIN_DINAS->value }}" class="text-amber-600 focus:ring-amber-500">
                                    </div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Super Admin DP2KBP3A Kab. Bangka</span>
                                </label>
                            </div>
                            @error('role') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Lengkap &amp; Gelar <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="name"
                                   type="text"
                                   placeholder="Contoh: Ahmad Fauzi, S.Sos / Admin DP2KBP3A"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Alamat Email Dinas <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="email"
                                   type="email"
                                   placeholder="nama@simentilin.bangka.go.id"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('email') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Territory Cascading (If Petugas Lapangan) -->
                        @if($role === UserRole::PETUGAS_LAPANGAN->value)
                            <div class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <p class="text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">
                                        Penetapan Wilayah Tugas Binaan
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <!-- Kecamatan -->
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Kecamatan <span class="text-red-500">*</span>
                                        </label>
                                        <select wire:model.live="kecamatan_id"
                                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                            <option value="">-- Pilih Kecamatan --</option>
                                            @foreach($kecamatans as $kec)
                                                <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('kecamatan_id') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <!-- Desa -->
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Desa / Kelurahan <span class="text-slate-400 font-normal">(Opsional)</span>
                                        </label>
                                        <select wire:model="desa_id"
                                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                            <option value="">Semua Desa (Koordinator)</option>
                                            @foreach($formDesas as $desa)
                                                <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('desa_id') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Password -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Kata Sandi <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="password"
                                       type="password"
                                       placeholder="Minimal 8 karakter"
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                                @error('password') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                    Ulangi Kata Sandi <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="password_confirmation"
                                       type="password"
                                       placeholder="Ketik ulang kata sandi"
                                       class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="$set('showCreateModal', false)"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow-lg shadow-[#008060]/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Simpan Akun Pengguna</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 2: EDIT PENGGUNA
         ════════════════════════════════════════════════════════════ -->
    @if($showEditModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-edit-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showEditModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 560px;">
                    
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-edit-title">
                                Edit Akun Pengguna
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                Perbarui biodata, peran, atau wilayah penugasan petugas.
                            </p>
                        </div>
                        <button wire:click="$set('showEditModal', false)"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <form wire:submit="updateUser" class="space-y-4 mt-5">
                        
                        <!-- Role -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                                Peran Akun (Role) <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="relative flex flex-col p-3.5 rounded-2xl border cursor-pointer transition {{ $role === UserRole::PETUGAS_LAPANGAN->value ? 'border-[#008060] bg-emerald-50/50 dark:bg-emerald-950/30 ring-2 ring-[#008060]/30' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">Petugas Lapangan</span>
                                        <input type="radio" wire:model.live="role" value="{{ UserRole::PETUGAS_LAPANGAN->value }}" class="text-[#008060] focus:ring-[#008060]">
                                    </div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">PKB / PLKB</span>
                                </label>

                                <label class="relative flex flex-col p-3.5 rounded-2xl border cursor-pointer transition {{ $role === UserRole::ADMIN_DINAS->value ? 'border-amber-500 bg-amber-50/50 dark:bg-amber-950/30 ring-2 ring-amber-500/30' : 'border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-750' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-bold text-slate-900 dark:text-white">Admin Dinas</span>
                                        <input type="radio" wire:model.live="role" value="{{ UserRole::ADMIN_DINAS->value }}" class="text-amber-600 focus:ring-amber-500">
                                    </div>
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Super Admin DP2KBP3A</span>
                                </label>
                            </div>
                            @error('role') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Name -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Nama Lengkap &amp; Gelar <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="name"
                                   type="text"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('name') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Alamat Email Dinas <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="email"
                                   type="email"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('email') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <!-- Territory Cascading -->
                        @if($role === UserRole::PETUGAS_LAPANGAN->value)
                            <div class="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 space-y-3">
                                <div class="flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <p class="text-xs font-bold text-emerald-900 dark:text-emerald-300 uppercase tracking-wider">
                                        Penetapan Wilayah Tugas Binaan
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Kecamatan <span class="text-red-500">*</span>
                                        </label>
                                        <select wire:model.live="kecamatan_id"
                                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                            <option value="">-- Pilih Kecamatan --</option>
                                            @foreach($kecamatans as $kec)
                                                <option value="{{ $kec->id }}">{{ $kec->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('kecamatan_id') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-[11px] font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                            Desa / Kelurahan <span class="text-slate-400 font-normal">(Opsional)</span>
                                        </label>
                                        <select wire:model="desa_id"
                                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                                            <option value="">Semua Desa (Koordinator)</option>
                                            @foreach($formDesas as $desa)
                                                <option value="{{ $desa->id }}">{{ $desa->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('desa_id') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="$set('showEditModal', false)"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-xs font-bold shadow-lg shadow-[#008060]/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                <span>Perbarui Data</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 3: RESET PASSWORD
         ════════════════════════════════════════════════════════════ -->
    @if($showResetPasswordModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-reset-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showResetPasswordModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all mx-auto text-left" style="max-width: 480px;">
                    
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900 dark:text-white" id="modal-reset-title">
                                    Atur Ulang Kata Sandi
                                </h3>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate max-w-[200px]">
                                    Pengguna: {{ $targetUserName }}
                                </p>
                            </div>
                        </div>
                        <button wire:click="$set('showResetPasswordModal', false)"
                                type="button"
                                class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>



                    <form wire:submit="resetPassword" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Kata Sandi Baru <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="new_password"
                                   type="password"
                                   placeholder="Minimal 8 karakter"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            @error('new_password') <span class="text-xs text-red-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                                Ulangi Kata Sandi Baru <span class="text-red-500">*</span>
                            </label>
                            <input wire:model="new_password_confirmation"
                                   type="password"
                                   placeholder="Ketik ulang kata sandi baru"
                                   class="w-full px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                            <button wire:click="$set('showResetPasswordModal', false)"
                                    type="button"
                                    class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                                Batal
                            </button>
                            <button type="submit"
                                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold shadow-lg shadow-amber-600/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                                <span>Simpan Kata Sandi</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    @endif

    <!-- ════════════════════════════════════════════════════════════
         MODAL 4: KONFIRMASI HAPUS PENGGUNA
         ════════════════════════════════════════════════════════════ -->
    @if($showDeleteModal)
        <div class="fixed inset-0 z-999999 overflow-y-auto" aria-labelledby="modal-delete-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-950/70 backdrop-blur-xs transition-opacity"
                 wire:click="$set('showDeleteModal', false)"></div>

            <div class="flex min-h-screen items-center justify-center p-4 relative z-10">
                <div class="relative w-full rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-2xl border border-slate-200 dark:border-slate-700 transition-all text-center mx-auto" style="max-width: 440px;">
                    
                    <div class="w-16 h-16 rounded-2xl bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 mx-auto flex items-center justify-center mb-4">
                        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>

                    <h3 class="text-lg font-bold text-slate-900 dark:text-white" id="modal-delete-title">
                        Hapus Akun Pengguna?
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
                        Apakah Anda yakin ingin menghapus akun <strong class="text-slate-900 dark:text-white">{{ $deletingUserName }}</strong>? Tindakan ini bersifat permanen dan tidak dapat dibatalkan.
                    </p>

                    <div class="flex items-center justify-center gap-3 mt-6">
                        <button wire:click="$set('showDeleteModal', false)"
                                type="button"
                                class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-semibold transition">
                            Batalkan
                        </button>
                        <button wire:click="deleteUser"
                                type="button"
                                class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-red-600 hover:bg-red-500 text-white text-xs font-bold shadow-lg shadow-red-600/30 transition data-loading:opacity-50 data-loading:pointer-events-none">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Ya, Hapus Akun</span>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    @endif

</div>
