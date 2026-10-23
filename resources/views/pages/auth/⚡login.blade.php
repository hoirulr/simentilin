<?php

use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Masuk Sistem | SI-MENTILIN')] class extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 6 karakter.',
        ];
    }

    public function login()
    {
        $this->validate();

        $throttleKey = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Terlalu banyak percobaan masuk. Silakan tunggu {$seconds} detik lagi.");
            return;
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('email', 'Kredensial email atau kata sandi tidak cocok dengan basis data.');
            return;
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
};
?>

<div class="w-full bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
    <div class="grid grid-cols-1 lg:grid-cols-12 min-h-[580px]">

        <!-- Left Visual Panel (5 cols) -->
        <div class="lg:col-span-5 bg-gradient-to-br from-[#0B2E59] via-[#0e3666] to-[#008060] p-6 sm:p-10 text-white flex flex-col justify-between relative overflow-hidden">
            <!-- Background Light Orbs -->
            <div class="absolute -right-10 -bottom-10 w-44 h-44 rounded-full bg-emerald-400/20 blur-2xl pointer-events-none"></div>
            <div class="absolute -left-10 -top-10 w-44 h-44 rounded-full bg-blue-400/20 blur-2xl pointer-events-none"></div>

            <div class="relative z-10 space-y-6">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/10 backdrop-blur-md text-emerald-300 text-xs font-semibold border border-white/15">
                    <span class="w-2 h-2 rounded-full bg-[#10B981] animate-ping"></span>
                    <span>Portal Resmi DP2KBP3A Kab. Bangka</span>
                </div>

                <div class="space-y-3">
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug">
                        SI-MENTILIN
                    </h2>
                    <p class="text-emerald-200 font-semibold text-sm">
                        Sistem Skrining Lima Indikator PUS &amp; Penanda Sasaran MUPAR
                    </p>
                    <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                        Sistem informasi terpadu Dinas Pengendalian Penduduk, Keluarga Berencana, Pemberdayaan Perempuan dan Perlindungan Anak Kabupaten Bangka untuk akselerasi penapisan KB dan intervensi keluarga berkualitas.
                    </p>
                </div>
            </div>

            <!-- Bottom Note -->
            <div class="relative z-10 pt-6 mt-8 border-t border-white/10 text-[11px] text-slate-300 flex items-center justify-between">
                <span>Skrining 5 Indikator (0–15)</span>
                <span class="font-semibold text-emerald-300">Bangka Berdaya</span>
            </div>
        </div>

        <!-- Right Login Form Panel (7 cols) -->
        <div class="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-center bg-white dark:bg-slate-900">
            <div class="max-w-md w-full mx-auto">

                <div class="mb-6">
                    <h3 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        Masuk ke Akun Anda
                    </h3>
                    <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Silakan masukkan kredensial resmi Anda untuk mengelola data PUS dan skrining.
                    </p>
                </div>

                <!-- Form -->
                <form wire:submit="login" class="space-y-4">

                    <!-- Email Input -->
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Alamat Email
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.206"/>
                                </svg>
                            </div>
                            <input id="email"
                                   type="email"
                                   wire:model="email"
                                   placeholder="nama@simentilin.bangka.go.id"
                                   class="w-full pl-11 pr-4 py-2.5 rounded-xl border text-sm text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 focus:bg-white dark:focus:bg-slate-800 focus:outline-none transition {{ $errors->has('email') ? 'border-red-500 ring-1 ring-red-500' : 'border-slate-300 dark:border-slate-700 focus:border-[#008060] focus:ring-1 focus:ring-[#008060]' }}"
                                   required
                                   autocomplete="email"
                                   autofocus>
                        </div>
                        @error('email')
                            <p class="text-xs text-red-500 font-medium mt-1.5 flex items-center gap-1">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <!-- Password Input with Show/Hide Alpine Toggle -->
                    <div x-data="{ show: false }">
                        <label for="password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Kata Sandi
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <input id="password"
                                   :type="show ? 'text' : 'password'"
                                   wire:model="password"
                                   placeholder="••••••••"
                                   class="w-full pl-11 pr-11 py-2.5 rounded-xl border text-sm text-slate-900 dark:text-white bg-slate-50 dark:bg-slate-800/80 focus:bg-white dark:focus:bg-slate-800 focus:outline-none transition {{ $errors->has('password') ? 'border-red-500 ring-1 ring-red-500' : 'border-slate-300 dark:border-slate-700 focus:border-[#008060] focus:ring-1 focus:ring-[#008060]' }}"
                                   required
                                   autocomplete="current-password">

                            <button type="button"
                                    @click="show = !show"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                                <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" style="display: none;">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                            <p class="text-xs text-red-500 font-medium mt-1.5 flex items-center gap-1">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <span>{{ $message }}</span>
                            </p>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   wire:model="remember"
                                   class="w-4 h-4 rounded text-[#008060] focus:ring-[#008060] border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800">
                            <span class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                Ingat sesi login saya
                            </span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-gradient-to-r from-[#0B2E59] to-[#008060] hover:from-[#092241] hover:to-[#006e52] text-white font-bold text-sm shadow-lg shadow-[#008060]/25 transition duration-150 focus:outline-none focus:ring-2 focus:ring-[#008060] focus:ring-offset-2 disabled:opacity-50"
                                data-loading:opacity-75>
                            <span wire:loading.remove wire:target="login">Masuk ke Sistem</span>
                            <span wire:loading wire:target="login" class="flex items-center gap-2">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Memverifikasi...</span>
                            </span>
                        </button>
                    </div>

                </form>

                <!-- Security Note -->
                <div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-center gap-2 text-[11px] text-slate-400">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span>Autentikasi Aman &amp; Terintegrasi DP2KBP3A Kab. Bangka</span>
                </div>

            </div>
        </div>

    </div>
</div>
