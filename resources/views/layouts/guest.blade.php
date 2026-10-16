<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Masuk' }} | SI-MENTILIN DP2KBP3A Kab. Bangka</title>

    <!-- Google Fonts: Instrument Sans -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Anti-Flash Script (Default ke Light Mode bawaan TailAdmin) -->
    <script>
        (function() {
            if (localStorage.getItem('simentilin_theme_v') !== 'light_v2') {
                localStorage.removeItem('simentilin_dark');
                localStorage.setItem('simentilin_theme_v', 'light_v2');
                document.documentElement.classList.remove('dark');
            } else if (localStorage.getItem('simentilin_dark') === 'true') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();

        window.toggleDarkMode = window.toggleDarkMode || function() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('simentilin_dark', isDark ? 'true' : 'false');
            if (window.Alpine && window.Alpine.store && window.Alpine.store('tailadmin')) {
                window.Alpine.store('tailadmin').darkMode = isDark;
            }
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: isDark } }));
            return isDark;
        };
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 flex flex-col justify-between"
      x-data
      x-init="$store.tailadmin && $store.tailadmin.init()">

    <!-- Top Ambient Lights -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-[650px] h-[350px] bg-gradient-to-b from-[#008060]/20 to-transparent blur-3xl opacity-60"></div>
        <div class="absolute top-1/4 -left-20 w-80 h-80 rounded-full bg-[#0B2E59]/20 blur-3xl opacity-50"></div>
        <div class="absolute bottom-10 right-0 w-96 h-96 rounded-full bg-emerald-500/10 blur-3xl opacity-50"></div>
    </div>

    <!-- Top Guest Header Bar -->
    <header class="relative z-10 w-full px-4 sm:px-8 py-5 flex items-center justify-between">
        <a href="/" wire:navigate class="flex items-center gap-2 group transition hover:opacity-90">
            <x-mentilin-logo size="md" textColor="text-slate-800 dark:text-white" />
        </a>

        <div class="flex items-center gap-3">
            <!-- Back to Landing Page -->
            <a href="/" wire:navigate
               class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-xl text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200/60 dark:hover:bg-slate-800 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Beranda</span>
            </a>

            <!-- Dark Mode Toggle -->
            <button type="button"
                    id="guest-theme-toggle-btn"
                    @click="$store.tailadmin ? $store.tailadmin.toggleDarkMode() : window.toggleDarkMode()"
                    onclick="if(!window.Alpine){window.toggleDarkMode();}"
                    class="p-2 text-slate-500 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 transition shadow-xs ring-1 ring-slate-200 dark:ring-slate-800 cursor-pointer"
                    title="Ganti Mode Terang / Gelap"
                    aria-label="Toggle Dark Mode">
                <!-- Sun Icon -->
                <svg class="w-5 h-5 text-amber-400 dark-icon-sun" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                <!-- Moon Icon -->
                <svg class="w-5 h-5 text-slate-600 dark-icon-moon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
            </button>
        </div>
    </header>

    <!-- Main Guest Slot Container -->
    <main class="relative z-10 flex-1 flex items-center justify-center px-4 sm:px-6 py-6 sm:py-10">
        <div class="w-full max-w-4xl mx-auto">
            {{ $slot ?? '' }}
            @yield('content')
        </div>
    </main>

    <!-- Guest Footer -->
    <footer class="relative z-10 w-full px-4 sm:px-8 py-4 text-center text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200/80 dark:border-slate-800/80 bg-white/40 dark:bg-slate-900/40 backdrop-blur-xs">
        <div class="max-w-4xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <span class="font-bold text-[#008060] dark:text-emerald-400">SI-MENTILIN</span>
                <span>&bull; Skrining &amp; Identifikasi 5 Indikator</span>
            </div>
            <div>
                &copy; {{ date('Y') }} DP2KBP3A Kab. Bangka. Hak Cipta Dilindungi.
            </div>
        </div>
    </footer>

@livewireScripts
</body>
</html>
