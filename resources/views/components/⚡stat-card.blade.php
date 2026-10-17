<?php

use Livewire\Component;

new class extends Component
{
    public string $title = '';
    public string $value = '';
    public string $subtitle = '';
    public string $badge = '';
    public string $badgeType = 'positive'; // positive, negative, warning, purple, neutral
    public string $color = 'teal'; // teal, navy, emerald, purple, amber, rose
    public string $icon = 'dashboard';

    public function mount(
        string $title = '',
        string $value = '',
        string $subtitle = '',
        string $badge = '',
        string $badgeType = 'positive',
        string $color = 'teal',
        string $icon = 'dashboard'
    ): void {
        $this->title = $title;
        $this->value = $value;
        $this->subtitle = $subtitle;
        $this->badge = $badge;
        $this->badgeType = $badgeType;
        $this->color = $color;
        $this->icon = $icon;
    }
};
?>

@php
    $colorConfig = match($color) {
        'navy' => [
            'iconBg' => 'bg-[#0B2E59]/10 dark:bg-[#0B2E59]/30 text-[#0B2E59] dark:text-blue-300',
            'border' => 'border-slate-200 dark:border-slate-700',
            'valueText' => 'text-[#0B2E59] dark:text-white',
        ],
        'emerald' => [
            'iconBg' => 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300',
            'border' => 'border-emerald-200 dark:border-emerald-900/60',
            'valueText' => 'text-emerald-700 dark:text-emerald-300',
        ],
        'purple' => [
            'iconBg' => 'bg-purple-100 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300',
            'border' => 'border-purple-200 dark:border-purple-900/60',
            'valueText' => 'text-purple-700 dark:text-purple-300',
        ],
        'amber' => [
            'iconBg' => 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300',
            'border' => 'border-amber-200 dark:border-amber-900/60',
            'valueText' => 'text-amber-700 dark:text-amber-300',
        ],
        'rose' => [
            'iconBg' => 'bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300',
            'border' => 'border-rose-200 dark:border-rose-900/60',
            'valueText' => 'text-rose-700 dark:text-rose-300',
        ],
        default => [ // teal
            'iconBg' => 'bg-[#008060]/10 dark:bg-[#008060]/30 text-[#008060] dark:text-emerald-300',
            'border' => 'border-slate-200 dark:border-slate-700',
            'valueText' => 'text-slate-900 dark:text-white',
        ],
    };

    $badgeClass = match($badgeType) {
        'negative' => 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        'warning' => 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        'purple' => 'bg-purple-50 text-purple-800 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
        'neutral' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
        default => 'bg-emerald-50 text-emerald-800 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
    };
@endphp

<div class="rounded-3xl bg-white dark:bg-slate-800 p-5 shadow-xs border {{ $colorConfig['border'] }} flex flex-col justify-between transition-all hover:shadow-md">
    <div class="flex items-center justify-between gap-3">
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            {{ $title }}
        </span>

        <div class="w-10 h-10 rounded-2xl flex items-center justify-center font-bold shrink-0 {{ $colorConfig['iconBg'] }}">
            @if($icon === 'users')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            @elseif($icon === 'screening')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            @elseif($icon === 'mupar')
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                </svg>
            @elseif($icon === 'money')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            @elseif($icon === 'chart')
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
            @else
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <div class="flex items-baseline justify-between gap-2">
            <h3 class="text-2xl font-black tracking-tight {{ $colorConfig['valueText'] }}">
                {{ $value }}
            </h3>
            @if($badge)
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold border {{ $badgeClass }}">
                    {{ $badge }}
                </span>
            @endif
        </div>

        @if($subtitle)
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ $subtitle }}
            </p>
        @endif
    </div>
</div>
