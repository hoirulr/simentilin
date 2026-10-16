@props([
    'size' => 'md', // sm, md, lg
    'withText' => true,
    'textColor' => 'text-white',
])

@php
    $sizeClasses = match($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-14 h-14',
        default => 'w-10 h-10',
    };
@endphp

<div class="flex items-center gap-3">
    <!-- Maskot Mentilin (Tarsius Bangka) Emblem -->
    <div class="{{ $sizeClasses }} relative flex-shrink-0 flex items-center justify-center rounded-xl bg-gradient-to-br from-[#008060] to-[#004d3a] p-1.5 shadow-lg shadow-[#008060]/30 ring-2 ring-emerald-400/30">
        <svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full drop-shadow-sm">
            <!-- Leaf / Health Halo -->
            <path d="M50 16C50 16 54 26 48 34C42 42 32 44 32 44C32 44 36 34 42 26C48 18 50 16 50 16Z" fill="#10B981" opacity="0.4"/>
            <!-- Mentilin Head -->
            <ellipse cx="32" cy="34" rx="20" ry="17" fill="#E8B88A"/>
            <path d="M12 34C12 24.6 20.9 17 32 17C43.1 17 52 24.6 52 34C52 40 47.5 45.5 40 48.5C35 50.5 29 50.5 24 48.5C16.5 45.5 12 40 12 34Z" fill="#D49A6A"/>
            <!-- Big Ears (Tarsier) -->
            <ellipse cx="14" cy="22" rx="7" ry="9" transform="rotate(-20 14 22)" fill="#C28353"/>
            <ellipse cx="14" cy="22" rx="4.5" ry="6.5" transform="rotate(-20 14 22)" fill="#F5C7A9"/>
            <ellipse cx="50" cy="22" rx="7" ry="9" transform="rotate(20 50 22)" fill="#C28353"/>
            <ellipse cx="50" cy="22" rx="4.5" ry="6.5" transform="rotate(20 50 22)" fill="#F5C7A9"/>
            <!-- Huge Iconic Mentilin Eyes (Luminous Amber / Gold) -->
            <!-- Left Eye -->
            <circle cx="23" cy="33" r="9" fill="#1E293B"/>
            <circle cx="23" cy="33" r="7.5" fill="#F59E0B"/>
            <circle cx="23" cy="33" r="6" fill="#D97706"/>
            <circle cx="23" cy="33" r="4.2" fill="#0F172A"/>
            <!-- Eye Highlights -->
            <circle cx="21" cy="31" r="2" fill="#FFFFFF"/>
            <circle cx="24.5" cy="34.5" r="0.9" fill="#FFFFFF"/>
            <!-- Right Eye -->
            <circle cx="41" cy="33" r="9" fill="#1E293B"/>
            <circle cx="41" cy="33" r="7.5" fill="#F59E0B"/>
            <circle cx="41" cy="33" r="6" fill="#D97706"/>
            <circle cx="41" cy="33" r="4.2" fill="#0F172A"/>
            <!-- Eye Highlights -->
            <circle cx="39" cy="31" r="2" fill="#FFFFFF"/>
            <circle cx="42.5" cy="34.5" r="0.9" fill="#FFFFFF"/>
            <!-- Cute Small Nose & Mouth -->
            <ellipse cx="32" cy="41" rx="2" ry="1.3" fill="#8B4513"/>
            <path d="M30 43.5C31 44.5 33 44.5 34 43.5" stroke="#8B4513" stroke-width="1.2" stroke-linecap="round"/>
            <!-- Mentilin Tiny Hands Clutching -->
            <ellipse cx="25" cy="51" rx="3" ry="2" fill="#D49A6A"/>
            <ellipse cx="39" cy="51" rx="3" ry="2" fill="#D49A6A"/>
        </svg>
    </div>

    @if($withText)
        <div class="flex flex-col">
            <span class="text-base font-extrabold tracking-wider {{ $textColor }} leading-tight flex items-center gap-1.5">
                SI-MENTILIN
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded-full bg-[#10B981] text-white tracking-normal uppercase">v2.0</span>
            </span>
            <span class="text-[10px] font-medium text-slate-300 dark:text-slate-400 tracking-tight leading-none mt-0.5">
                DP2KBP3A Kab. Bangka
            </span>
        </div>
    @endif
</div>
