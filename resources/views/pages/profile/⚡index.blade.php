<?php

use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Profil Pengguna & Keamanan | SI-MENTILIN')] class extends Component {
    // Profile info state
    public string $name = '';
    public string $email = '';

    // Password change state
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        if ($user) {
            $this->name = $user->name;
            $this->email = $user->email;
        }
    }

    public function updateProfile(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini telah digunakan oleh akun lain.',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        session()->flash('profile_success', 'Data profil Anda berhasil diperbarui!');
    }

    public function updatePassword(): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed', 'different:current_password'],
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'current_password.current_password' => 'Kata sandi saat ini tidak cocok dengan rekam sistem.',
            'password.required' => 'Kata sandi baru wajib diisi.',
            'password.min' => 'Kata sandi baru minimal harus 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi lama.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        session()->flash('password_success', 'Kata sandi akun Anda berhasil diperbarui!');
    }

    public function with(): array
    {
        $user = auth()->user();
        $isAdmin = $user->isAdminDinas();

        // Stats calculation
        $stats = [];
        if ($isAdmin) {
            $stats = [
                'total_petugas' => User::where('role', 'petugas_lapangan')->count(),
                'total_pus' => PusProfile::count(),
                'total_screening' => Screening::count(),
            ];
        } else {
            $stats = [
                'pus_terdaftar' => PusProfile::where('user_id', $user->id)->count(),
                'screening_dibuat' => Screening::where('user_id', $user->id)->count(),
                'desa_binaan' => $user->desa?->name ?? 'Wilayah Kecamatan',
            ];
        }

        return [
            'currentUser' => $user,
            'isAdmin' => $isAdmin,
            'stats' => $stats,
        ];
    }
};
?>

<div class="space-y-6">

    <!-- Page Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0B2E59] via-[#0e3a6e] to-[#008060] p-6 sm:p-8 text-white shadow-xl">
        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center font-black text-2xl shadow-lg border-2 border-white/20 {{ $isAdmin ? 'bg-amber-400 text-slate-900' : 'bg-emerald-400 text-slate-900' }}">
                    {{ $currentUser->initials() }}
                </div>
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-black tracking-tight text-white">
                            {{ $currentUser->name }}
                        </h2>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold shadow-sm {{ $isAdmin ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900' }}">
                            {{ $isAdmin ? 'Admin Dinas' : 'Petugas Lapangan' }}
                        </span>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-200">
                        {{ $currentUser->email }} &bull; Bergabung sejak {{ $currentUser->created_at?->translatedFormat('d F Y') }}
                    </p>
                </div>
            </div>

            <!-- Quick Territory Chip -->
            <div class="px-4 py-3 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 text-xs">
                <span class="text-[10px] uppercase font-bold text-emerald-300 block">Wilayah Penugasan</span>
                <span class="font-bold text-white mt-0.5 block">
                    @if($isAdmin)
                        Seluruh Wilayah Kab. Bangka (8 Kecamatan)
                    @else
                        {{ $currentUser->desa ? 'Desa/Kel. ' . $currentUser->desa->name : '' }}
                        {{ $currentUser->kecamatan ? '(Kec. ' . $currentUser->kecamatan->name . ')' : 'Kabupaten Bangka' }}
                    @endif
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Stats Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @if($isAdmin)
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Petugas Lapangan Aktif</span>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ $stats['total_petugas'] }} Akun</p>
                <p class="text-[11px] text-slate-500 mt-1">Tersebar di 8 Kecamatan</p>
            </div>

            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Total Sasaran PUS</span>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ number_format($stats['total_pus']) }} PUS</p>
                <p class="text-[11px] text-slate-500 mt-1">Basis data Kabupaten Bangka</p>
            </div>

            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider block">Audit Skrining Masuk</span>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ number_format($stats['total_screening']) }} Berkas</p>
                <p class="text-[11px] text-slate-500 mt-1">Skrining 5 indikator</p>
            </div>
        @else
            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Wilayah Binaan PKB</span>
                <p class="text-xl font-black text-slate-900 dark:text-white mt-2 truncate">{{ $stats['desa_binaan'] }}</p>
                <p class="text-[11px] text-slate-500 mt-1">Kec. {{ $currentUser->kecamatan?->name ?? '-' }}</p>
            </div>

            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">PUS Didaftarkan</span>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ number_format($stats['pus_terdaftar']) }} PUS</p>
                <p class="text-[11px] text-slate-500 mt-1">Oleh akun Anda</p>
            </div>

            <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider block">Skrining Selesai</span>
                <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ number_format($stats['screening_dibuat']) }} Kali</p>
                <p class="text-[11px] text-slate-500 mt-1">Hasil wawancara lapangan</p>
            </div>
        @endif
    </div>

    <!-- Forms Section: 2 Columns Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Form 1: Ubah Informasi Profil -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-6 shadow-xs border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#008060] dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Informasi Akun</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Perbarui nama pengguna dan alamat email kedinasan</p>
                    </div>
                </div>

                @if(session('profile_success'))
                    <div class="mt-4 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ session('profile_success') }}</span>
                    </div>
                @endif

                <form wire:submit="updateProfile" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Nama Lengkap <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="name"
                               type="text"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Alamat Email <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="email"
                               type="email"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                            Peran Pengguna (Hak Akses)
                        </label>
                        <input type="text"
                               disabled
                               value="{{ $isAdmin ? 'Super Admin / Dinas DP2KBP3A' : 'Petugas Lapangan (PKB/PLKB)' }}"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-slate-500 cursor-not-allowed">
                        <p class="text-[10px] text-slate-400 mt-1">Peran akun diatur oleh Administrator Dinas</p>
                    </div>

                    <div class="pt-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#008060] hover:bg-[#006e52] shadow-md shadow-[#008060]/20 transition data-loading:opacity-50">
                            <span wire:loading.remove wire:target="updateProfile">Simpan Perubahan Profil</span>
                            <span wire:loading wire:target="updateProfile">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Form 2: Ganti Password -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-6 shadow-xs border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
            <div>
                <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-700">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#0B2E59] dark:text-blue-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Keamanan &amp; Kata Sandi</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Ganti kata sandi akun untuk menjaga keamanan akses</p>
                    </div>
                </div>

                @if(session('password_success'))
                    <div class="mt-4 p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-800 dark:text-emerald-300 font-semibold flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>{{ session('password_success') }}</span>
                    </div>
                @endif

                <form wire:submit="updatePassword" class="mt-5 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Kata Sandi Saat Ini <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="current_password"
                               type="password"
                               placeholder="••••••••"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @error('current_password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Kata Sandi Baru <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="password"
                               type="password"
                               placeholder="Minimal 8 karakter"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Konfirmasi Kata Sandi Baru <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="password_confirmation"
                               type="password"
                               placeholder="Ketik ulang kata sandi baru"
                               class="w-full px-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                    </div>

                    <div class="pt-3">
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0B2E59] hover:bg-[#082242] shadow-md shadow-[#0B2E59]/20 transition data-loading:opacity-50">
                            <span wire:loading.remove wire:target="updatePassword">Perbarui Kata Sandi</span>
                            <span wire:loading wire:target="updatePassword">Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

</div>
