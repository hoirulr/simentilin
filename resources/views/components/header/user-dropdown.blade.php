@php
    $currentUser = auth()->user();
    $isAdmin = $currentUser?->isAdminDinas();
@endphp

<div class="relative" x-data="{
    isOpen: false,
    subDropdownOpen: false,
    currentLocale: 'id',
    languages: [
        {
            id: 'id',
            name: 'Bahasa Indonesia',
            shortName: 'ID',
            flag: 'flag-us.svg',
            dir: 'ltr'
        },
        {
            id: 'en',
            name: 'English',
            shortName: 'EN',
            flag: 'flag-us.svg',
            dir: 'ltr'
        }
    ],
    get currentLang() {
        return this.languages.find(l => l.id === this.currentLocale) || this.languages[0];
    },
    toggleDropdown() {
        this.isOpen = !this.isOpen;
        if (!this.isOpen) {
            this.subDropdownOpen = false;
        }
    },
    closeDropdown() {
        this.isOpen = false;
        this.subDropdownOpen = false;
    },
    selectLanguage(lang) {
        this.currentLocale = lang.id;
    }
}" @click.outside="closeDropdown()">

    @if($currentUser)
        <!-- User Trigger -->
        <button
            class="flex items-center text-gray-700 dark:text-gray-400 group focus:outline-none cursor-pointer"
            type="button"
            @click="toggleDropdown()"
        >
            <span class="mr-3 flex items-center justify-center font-bold text-xs rounded-full h-10 w-10 rtl:mr-0 rtl:ml-3 {{ $isAdmin ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300 ring-2 ring-amber-400/30' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300 ring-2 ring-emerald-400/30' }}">
                {{ $currentUser->initials() }}
            </span>

            <div class="hidden text-left xl:block mr-1 rtl:mr-0 rtl:ml-1">
                <span class="block font-medium text-theme-sm text-gray-800 dark:text-white leading-tight">
                    {{ $currentUser->name }}
                </span>
                <span class="block text-[10px] text-gray-500 dark:text-gray-400">
                    {{ $isAdmin ? 'Admin Dinas DP2KBP3A' : 'Petugas Lapangan' }}
                </span>
            </div>

            <!-- Chevron Down Icon -->
            <svg
                :class="isOpen ? 'rotate-180' : ''"
                class="transition-transform duration-200 stroke-gray-500 group-hover:stroke-gray-700 dark:stroke-gray-400 dark:group-hover:stroke-gray-200"
                width="18"
                height="18"
                viewBox="0 0 18 18"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <path
                    d="M4.5 6.75L9 11.25L13.5 6.75"
                    stroke="currentColor"
                    stroke-width="1.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </button>

        <!-- Dropdown Menu -->
        <div
            x-show="isOpen"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="transform opacity-0 scale-95"
            x-transition:enter-end="transform opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="transform opacity-100 scale-100"
            x-transition:leave-end="transform opacity-0 scale-95"
            class="absolute ltr:right-0 rtl:left-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark z-50"
            style="display: none;"
        >
            <!-- User Info -->
            <div class="pb-3 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2 mb-1">
                    <span class="block font-semibold text-gray-900 text-theme-sm dark:text-white truncate">
                        {{ $currentUser->name }}
                    </span>
                    <span class="px-1.5 py-0.5 text-[9px] font-bold rounded {{ $isAdmin ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300' }}">
                        {{ $isAdmin ? 'Admin' : 'Petugas' }}
                    </span>
                </div>
                <span class="block text-theme-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ $currentUser->email }}
                </span>
                <span class="mt-1 block text-[11px] text-gray-600 dark:text-gray-300">
                    Wilayah: <strong class="text-gray-800 dark:text-white">{{ $isAdmin ? 'Kab. Bangka' : ($currentUser->desa ? $currentUser->desa->name : ($currentUser->kecamatan ? 'Kec. ' . $currentUser->kecamatan->name : 'Bangka')) }}</strong>
                </span>
            </div>

            <!-- Menu Items -->
            <ul class="flex flex-col gap-1 pt-3 pb-2 border-b border-gray-100 dark:border-gray-800">
                <li>
                    <a
                        href="/dashboard"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    >
                        <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </span>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a
                        href="/screening/create"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    >
                        <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                        </span>
                        Skrining 5 Indikator
                    </a>
                <li>
                    <a
                        href="/profile"
                        wire:navigate
                        class="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
                    >
                        <span class="text-gray-500 group-hover:text-gray-700 dark:group-hover:text-gray-300">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        Profil &amp; Keamanan
                    </a>
                </li>
            </ul>

            <!-- Sign Out Form -->
            <form method="POST" action="{{ route('logout') }}" class="mt-2 block">
                @csrf
                <button
                    type="submit"
                    class="flex items-center w-full gap-3 px-3 py-2 font-medium text-red-600 rounded-lg group text-theme-sm hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10 transition cursor-pointer"
                >
                    <span class="text-red-500 group-hover:text-red-600 dark:group-hover:text-red-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </span>
                    Keluar Sistem
                </button>
            </form>
        </div>
    @else
        <!-- Guest Login Button -->
        <a href="{{ route('login') }}" wire:navigate
            class="flex items-center gap-2 px-3.5 py-2 font-medium text-white rounded-xl bg-brand-500 hover:bg-brand-600 text-theme-sm transition shadow-theme-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
            </svg>
            <span>Masuk</span>
        </a>
    @endif
</div>
