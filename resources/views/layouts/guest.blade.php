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

    <!-- Anti-Flash Script & TailAdmin Theme Store Setup -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.style.colorScheme = 'dark';
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.style.colorScheme = 'light';
            }
        })();

        document.addEventListener('alpine:init', () => {
            if (!Alpine.store('theme')) {
                Alpine.store('theme', {
                    init() {
                        const savedTheme = localStorage.getItem('theme');
                        this.theme = savedTheme === 'dark' ? 'dark' : 'light';
                        this.updateTheme();
                    },
                    theme: 'light',
                    resolvedTheme: 'light',
                    set(value) {
                        value = value === 'dark' ? 'dark' : 'light';
                        this.theme = value;
                        localStorage.setItem('theme', value);
                        this.updateTheme();
                        window.dispatchEvent(new CustomEvent('theme-changed', { detail: value }));
                    },
                    toggle() {
                        this.set(this.resolvedTheme === 'dark' ? 'light' : 'dark');
                    },
                    updateTheme() {
                        const html = document.documentElement;
                        const isDark = this.theme === 'dark';
                        if (isDark) {
                            html.classList.add('dark');
                        } else {
                            html.classList.remove('dark');
                        }

                        this.resolvedTheme = isDark ? 'dark' : 'light';
                        html.setAttribute('data-color-scheme', this.resolvedTheme);
                        html.dataset['theme'] = this.resolvedTheme;
                        html.style.colorScheme = this.resolvedTheme;
                        if (document.body) {
                            document.body.dataset['theme'] = this.resolvedTheme;
                            document.body.style.colorScheme = this.resolvedTheme;
                        }
                    }
                });
            }
        });

        window.toggleDarkMode = window.toggleDarkMode || function() {
            if (window.Alpine && window.Alpine.store && window.Alpine.store('theme')) {
                window.Alpine.store('theme').toggle();
                return window.Alpine.store('theme').resolvedTheme === 'dark';
            }
            const isDark = document.documentElement.classList.toggle('dark');
            const themeVal = isDark ? 'dark' : 'light';
            localStorage.setItem('theme', themeVal);
            document.documentElement.style.colorScheme = themeVal;
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: themeVal }));
            return isDark;
        };
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 flex flex-col justify-between"
      x-data>

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

            <!-- Dark / Light Mode Sliding Toggle Switch -->
            <button type="button"
                    id="guest-theme-toggle-btn"
                    @click="$store.theme ? $store.theme.toggle() : window.toggleDarkMode()"
                    onclick="if(!window.Alpine){window.toggleDarkMode();}"
                    class="relative inline-flex items-center justify-between h-8 w-16 shrink-0 cursor-pointer rounded-full p-1 bg-slate-200/90 hover:bg-slate-300/80 dark:bg-slate-800 dark:hover:bg-slate-700/80 border border-slate-300/80 dark:border-slate-700 transition-colors duration-200 shadow-2xs focus:outline-hidden"
                    role="switch"
                    :aria-checked="$store.theme ? $store.theme.resolvedTheme === 'dark' : document.documentElement.classList.contains('dark')"
                    title="Ganti Mode Terang / Gelap"
                    aria-label="Toggle Dark Mode">
                <!-- Left Track Icon: Sun (Amber in light mode, dimmed in dark mode) -->
                <span class="flex items-center justify-center w-6 h-6 text-amber-500/90 dark:text-slate-500 transition-colors pointer-events-none">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </span>

                <!-- Right Track Icon: Moon (Dimmed in light mode, emerald in dark mode) -->
                <span class="flex items-center justify-center w-6 h-6 text-slate-400 dark:text-emerald-400 transition-colors pointer-events-none">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </span>

                <!-- Sliding Indicator Thumb (Moves between Left = Sun in Light Mode, Right = Moon in Dark Mode) -->
                <span class="absolute top-1 left-1 flex items-center justify-center w-6 h-6 rounded-full bg-white dark:bg-slate-900 shadow-md ring-1 ring-slate-900/10 dark:ring-white/10 transition-transform duration-200 ease-in-out pointer-events-none translate-x-0 dark:translate-x-8">
                    <!-- Sun Icon in Thumb (Visible in Light Mode) -->
                    <svg class="w-3.5 h-3.5 text-amber-500 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <!-- Moon Icon in Thumb (Visible in Dark Mode) -->
                    <svg class="w-3.5 h-3.5 text-emerald-400 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                </span>
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
