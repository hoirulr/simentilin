<x-layouts.app title="Beranda Inovasi">

    <!-- Welcome Hero Banner (TailAdmin Light Mode Hero Card) -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-slate-800 p-6 sm:p-8 text-slate-800 dark:text-slate-100 shadow-sm border border-slate-200 dark:border-slate-700 mb-8">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-950/50 text-[#008060] dark:text-emerald-400 text-xs font-semibold mb-3 border border-emerald-200 dark:border-emerald-800/60">
                    <span class="w-2 h-2 rounded-full bg-[#008060] animate-ping"></span>
                    <span>DP2KBP3A Kabupaten Bangka &bull; Inovasi PIM III</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    SI-MENTILIN (Skrining Lima Indikator)
                </h2>
                <p class="mt-2 text-slate-600 dark:text-slate-300 text-sm sm:text-base leading-relaxed">
                    Sistem identifikasi presisi sasaran Pasangan Usia Subur (PUS) berdasarkan 5 parameter skoring (0–15) dan penanda strategis <strong class="text-[#008060] dark:text-emerald-400">MUPAR</strong> guna optimalisasi capaian MCPR &amp; penurunan TFR di 8 Kecamatan.
                </p>
                <div class="flex flex-wrap items-center gap-3 mt-5">
                    @auth
                        <a href="{{ route('dashboard') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white font-semibold text-sm shadow-md shadow-[#008060]/20 transition group">
                            <svg class="w-4 h-4 transition group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            <span>Buka Dashboard</span>
                        </a>
                        <a href="{{ route('screening.create') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm border border-slate-200 dark:border-slate-600 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>Skrining PUS Baru</span>
                        </a>
                    @else
                        <a href="{{ route('login') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white font-semibold text-sm shadow-md shadow-[#008060]/20 transition group">
                            <svg class="w-4 h-4 transition group-hover:scale-110" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                            </svg>
                            <span>Masuk ke Sistem (Login)</span>
                        </a>
                        <a href="{{ route('login') }}" wire:navigate
                           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-semibold text-sm border border-slate-200 dark:border-slate-600 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span>Lihat Info Modul &amp; Peran</span>
                        </a>
                    @endauth
                </div>
            </div>

            <!-- Mascot Illustration Card -->
            <div class="hidden md:flex flex-col items-center bg-slate-50 dark:bg-slate-700/50 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-2xs">
                <x-mentilin-logo size="lg" :withText="false" />
                <span class="text-xs font-bold text-slate-900 dark:text-white mt-2">Maskot Mentilin</span>
                <span class="text-[10px] text-[#008060] dark:text-emerald-400 font-semibold">Tarsius khas Bangka</span>
            </div>
        </div>
    </div>

    <!-- TailAdmin Data Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">

        <!-- Card 1: Total Profil PUS -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total PUS Terdata</span>
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ \App\Models\PusProfile::count() }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-[#008060]/10 dark:bg-[#008060]/20 text-[#008060] dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center gap-1.5 mt-3 text-xs text-slate-500 dark:text-slate-400">
                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">&bull; 8 Kecamatan</span>
                <span>terintegrasi se-Kab. Bangka</span>
            </div>
        </div>

        <!-- Card 2: Skrining Selesai -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Total Skrining 5 Indikator</span>
                    <h3 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                        {{ \App\Models\Screening::count() }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-500/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center gap-1.5 mt-3 text-xs text-slate-500 dark:text-slate-400">
                <span class="text-blue-600 dark:text-blue-400 font-semibold">Skor 0–15</span>
                <span>tervalidasi otomatis</span>
            </div>
        </div>

        <!-- Card 3: Prioritas Utama (MUPAR) -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Sasaran MUPAR</span>
                    <h3 class="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
                        {{ \App\Models\Screening::where('is_mupar', true)->count() }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center gap-1.5 mt-3 text-xs text-purple-600 dark:text-purple-400 font-medium">
                <span class="px-1.5 py-0.2 rounded bg-purple-100 dark:bg-purple-950/50 text-purple-700 dark:text-purple-300 font-bold text-[10px]">Flag MUPAR</span>
                <span>Muda Paritas Rendah</span>
            </div>
        </div>

        <!-- Card 4: Intervensi Terlaksana -->
        <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-5 shadow-xs hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Realisasi Intervensi</span>
                    <h3 class="text-2xl font-bold text-[#008060] dark:text-emerald-400 mt-1">
                        {{ \App\Models\Intervention::count() }}
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-center gap-1.5 mt-3 text-xs text-slate-500 dark:text-slate-400">
                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">Tercatat</span>
                <span>durasi, biaya, &amp; KB modern</span>
            </div>
        </div>

    </div>

    <!-- Semantic Priority Badges Demonstration (Design System Reference) -->
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-6 shadow-xs mb-8">
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
            <svg class="w-5 h-5 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
            </svg>
            <span>Standar Klasifikasi Badge Prioritas SI-MENTILIN</span>
        </h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-5">
            Sesuai panduan <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[#008060] font-mono text-xs">DESIGN_SYSTEM.md</code> dan rumus matriks <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-[#008060] font-mono text-xs">PRD.md &sect;3.2</code>:
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

            <!-- Prioritas Utama -->
            <div class="p-4 rounded-xl border border-red-200 dark:border-red-900/40 bg-red-50/50 dark:bg-red-950/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg border bg-red-100 text-red-800 border-red-600 dark:bg-red-950 dark:text-red-300">
                        Skor 11–15 (Tinggi)
                    </span>
                    <span class="px-2 py-0.5 text-[10px] rounded-md border bg-purple-100 text-purple-800 border-purple-400 font-bold dark:bg-purple-950 dark:text-purple-300">
                        + MUPAR
                    </span>
                </div>
                <h4 class="text-sm font-extrabold text-red-700 dark:text-red-400">#1 Prioritas Utama</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Penjangkauan dan konseling segera oleh PKB/PLKB</p>
            </div>

            <!-- Prioritas Tinggi -->
            <div class="p-4 rounded-xl border border-orange-200 dark:border-orange-900/40 bg-orange-50/50 dark:bg-orange-950/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg border bg-red-100 text-red-800 border-red-600 dark:bg-red-950 dark:text-red-300">
                        Skor 11–15 (Tinggi)
                    </span>
                    <span class="text-[11px] text-slate-400">Non-MUPAR</span>
                </div>
                <h4 class="text-sm font-extrabold text-orange-700 dark:text-orange-400">#2 Prioritas Tinggi</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Penjangkauan dan konseling segera</p>
            </div>

            <!-- Prioritas Edukasi Terjadwal -->
            <div class="p-4 rounded-xl border border-amber-200 dark:border-amber-900/40 bg-amber-50/50 dark:bg-amber-950/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg border bg-amber-100 text-amber-800 border-amber-500 dark:bg-amber-950 dark:text-amber-300">
                        Skor 7–10 (Sedang)
                    </span>
                    <span class="px-2 py-0.5 text-[10px] rounded-md border bg-purple-100 text-purple-800 border-purple-400 font-bold dark:bg-purple-950 dark:text-purple-300">
                        + MUPAR
                    </span>
                </div>
                <h4 class="text-sm font-extrabold text-amber-700 dark:text-amber-400">#3 Edukasi Terjadwal</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Penjangkauan / konseling terjadwal fokus MUPAR</p>
            </div>

            <!-- Monitoring Rutin -->
            <div class="p-4 rounded-xl border border-emerald-200 dark:border-emerald-900/40 bg-emerald-50/50 dark:bg-emerald-950/20">
                <div class="flex items-center justify-between mb-2">
                    <span class="px-2.5 py-1 text-xs font-bold rounded-lg border bg-emerald-100 text-emerald-800 border-emerald-500 dark:bg-emerald-950 dark:text-emerald-300">
                        Skor 0–6 (Rendah)
                    </span>
                    <span class="text-[11px] text-slate-400">&plusmn; MUPAR</span>
                </div>
                <h4 class="text-sm font-extrabold text-emerald-700 dark:text-emerald-400">#5 Monitoring Rutin</h4>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">Monitoring dan edukasi berkala</p>
            </div>

        </div>
    </div>

</x-layouts.app>
