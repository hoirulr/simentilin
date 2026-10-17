<?php

use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Rekap Wilayah & Lembar Kerja | SI-MENTILIN')] class extends Component {
    public ?int $selectedDesaId = null;
    public string $periodeBulan = 'all';
    public int $periodeTahun = 2026;

    public function mount(): void
    {
        $user = auth()->user();

        // Default to user's assigned desa if specific, or all desas in their territory
        if ($user && $user->desa_id) {
            $this->selectedDesaId = $user->desa_id;
        }

        $this->periodeTahun = (int) now()->year;
        $this->periodeBulan = (string) now()->month;
    }

    public function exportCsv()
    {
        $data = $this->getRekapData();
        $user = auth()->user();
        $wilayahLabel = $user->desa?->name ?? $user->kecamatan?->name ?? 'Wilayah-Binaan';
        $filename = 'rekap-wilayah-' . str($wilayahLabel)->slug() . '-' . $this->periodeTahun . '-' . $this->periodeBulan . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // CSV Header
            fputcsv($handle, [
                'No',
                'Kecamatan',
                'Desa / Kelurahan',
                'Kode Wilayah BPS',
                'Total PUS Sasaran',
                'PUS Diskrining',
                'Cakupan Skrining (%)',
                'Prioritas Utama (11-15 + MUPAR)',
                'Prioritas Tinggi',
                'Prioritas Sedang / Rendah',
                'Total MUPAR',
                'Intervensi Terlaksana',
                'Capaian Intervensi (%)',
            ]);

            foreach ($data['rekapDesa'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['kecamatan_name'],
                    $row['desa_name'],
                    $row['desa_code'],
                    $row['total_pus'],
                    $row['diskrining'],
                    $row['persen_cakupan'] . '%',
                    $row['prioritas_utama'],
                    $row['prioritas_tinggi'],
                    $row['prioritas_sedang_rendah'],
                    $row['total_mupar'],
                    $row['intervensi_selesai'],
                    $row['persen_intervensi'] . '%',
                ]);
            }

            // Summary row
            fputcsv($handle, [
                '',
                'TOTAL',
                'SELURUH BINAAN',
                '-',
                $data['summary']['totalPus'],
                $data['summary']['totalScreening'],
                $data['summary']['persenCakupan'] . '%',
                $data['summary']['prioritasUtama'],
                $data['summary']['prioritasTinggi'],
                $data['summary']['prioritasSedangRendah'],
                $data['summary']['totalMupar'],
                $data['summary']['totalIntervensi'],
                $data['summary']['persenIntervensi'] . '%',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getRekapData(): array
    {
        $user = auth()->user();

        // 1. Determine Accessible Desas
        $desasQuery = Desa::with('kecamatan')->orderBy('name');
        if (! $user->isAdminDinas()) {
            if ($user->desa_id) {
                $desasQuery->where('id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $desasQuery->where('kecamatan_id', $user->kecamatan_id);
            }
        } elseif ($this->selectedDesaId) {
            $desasQuery->where('id', $this->selectedDesaId);
        }

        if ($this->selectedDesaId && ! $user->desa_id) {
            $desasQuery->where('id', $this->selectedDesaId);
        }

        $desas = $desasQuery->get();

        // 2. Base Date Filters
        $applyDateFilter = function ($query, string $dateColumn = 'tanggal_skrining') {
            if ($this->periodeTahun) {
                $query->whereYear($dateColumn, $this->periodeTahun);
            }
            if ($this->periodeBulan !== 'all') {
                $query->whereMonth($dateColumn, (int) $this->periodeBulan);
            }
        };

        // 3. Build Rekap per Desa
        $rekapDesa = [];
        $sumPus = 0;
        $sumScreening = 0;
        $sumUtama = 0;
        $sumTinggi = 0;
        $sumSedangRendah = 0;
        $sumMupar = 0;
        $sumIntervensi = 0;

        foreach ($desas as $desa) {
            // Count total PUS in this desa
            $totalPus = PusProfile::where('desa_id', $desa->id)->count();

            // Screenings in this desa with date filter
            $screeningsQuery = Screening::whereHas('pusProfile', function ($q) use ($desa) {
                $q->where('desa_id', $desa->id);
            });
            $applyDateFilter($screeningsQuery, 'tanggal_skrining');

            $diskrining = (clone $screeningsQuery)->count();
            $prioritasUtama = (clone $screeningsQuery)
                ->where('total_skor', '>=', 11)
                ->where('is_mupar', true)
                ->count();
            $prioritasTinggi = (clone $screeningsQuery)
                ->where('total_skor', '>=', 11)
                ->where('is_mupar', false)
                ->count();
            $prioritasSedangRendah = (clone $screeningsQuery)
                ->where('total_skor', '<', 11)
                ->count();
            $totalMupar = (clone $screeningsQuery)
                ->where('is_mupar', true)
                ->count();

            // Interventions completed
            $intervensiSelesai = (clone $screeningsQuery)
                ->whereHas('interventions')
                ->count();

            $persenCakupan = $totalPus > 0 ? round(($diskrining / $totalPus) * 100, 1) : 0;
            $persenIntervensi = $diskrining > 0 ? round(($intervensiSelesai / $diskrining) * 100, 1) : 0;

            $rekapDesa[] = [
                'desa_id' => $desa->id,
                'desa_name' => $desa->name,
                'desa_code' => $desa->code,
                'kecamatan_name' => $desa->kecamatan?->name ?? '-',
                'total_pus' => $totalPus,
                'diskrining' => $diskrining,
                'persen_cakupan' => $persenCakupan,
                'prioritas_utama' => $prioritasUtama,
                'prioritas_tinggi' => $prioritasTinggi,
                'prioritas_sedang_rendah' => $prioritasSedangRendah,
                'total_mupar' => $totalMupar,
                'intervensi_selesai' => $intervensiSelesai,
                'persen_intervensi' => $persenIntervensi,
            ];

            $sumPus += $totalPus;
            $sumScreening += $diskrining;
            $sumUtama += $prioritasUtama;
            $sumTinggi += $prioritasTinggi;
            $sumSedangRendah += $prioritasSedangRendah;
            $sumMupar += $totalMupar;
            $sumIntervensi += $intervensiSelesai;
        }

        // 4. Urgent Unaddressed Priority Queue (Skor 11-15 + MUPAR without intervention)
        $urgentQuery = Screening::with(['pusProfile.desa', 'pusProfile.kecamatan'])
            ->forCurrentUser($user)
            ->where('total_skor', '>=', 11)
            ->where('is_mupar', true)
            ->whereDoesntHave('interventions')
            ->orderByDesc('tanggal_skrining')
            ->limit(10);
        $applyDateFilter($urgentQuery, 'tanggal_skrining');
        $urgentList = $urgentQuery->get();

        $overallCakupan = $sumPus > 0 ? round(($sumScreening / $sumPus) * 100, 1) : 0;
        $overallIntervensi = $sumScreening > 0 ? round(($sumIntervensi / $sumScreening) * 100, 1) : 0;

        return [
            'desasList' => $desas,
            'rekapDesa' => $rekapDesa,
            'urgentList' => $urgentList,
            'summary' => [
                'totalPus' => $sumPus,
                'totalScreening' => $sumScreening,
                'persenCakupan' => $overallCakupan,
                'prioritasUtama' => $sumUtama,
                'prioritasTinggi' => $sumTinggi,
                'prioritasSedangRendah' => $sumSedangRendah,
                'totalMupar' => $sumMupar,
                'totalIntervensi' => $sumIntervensi,
                'persenIntervensi' => $overallIntervensi,
            ],
        ];
    }

    public function with(): array
    {
        $user = auth()->user();
        $rekapData = $this->getRekapData();

        // Available desas for dropdown filter
        $availableDesasQuery = Desa::orderBy('name');
        if (! $user->isAdminDinas()) {
            if ($user->desa_id) {
                $availableDesasQuery->where('id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $availableDesasQuery->where('kecamatan_id', $user->kecamatan_id);
            }
        }
        $availableDesas = $availableDesasQuery->get();

        return array_merge($rekapData, [
            'availableDesas' => $availableDesas,
            'currentUser' => $user,
        ]);
    }
};
?>

<div class="space-y-6">

    <!-- ════════════════════════════════════════════════════════════
         PRINT-ONLY FORMAL KOP SURAT (Hanya Tampil Saat Cetak / PDF)
         ════════════════════════════════════════════════════════════ -->
    <div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4 text-center">
        <div class="flex items-center justify-center gap-4">
            <div class="text-left">
                <h3 class="text-xs font-bold tracking-widest text-slate-700 uppercase">Pemerintah Kabupaten Bangka</h3>
                <h2 class="text-sm font-extrabold text-slate-900 uppercase">Dinas Pengendalian Penduduk, KB, Pemberdayaan Perempuan dan Perlindungan Anak</h2>
                <p class="text-[10px] text-slate-600">Jl. Pemuda No. 1 Sungailiat, Kabupaten Bangka, Kepulauan Bangka Belitung</p>
            </div>
        </div>
        <div class="mt-4 pt-2 border-t border-slate-400 text-center">
            <h4 class="text-xs font-bold uppercase underline">LEMBAR REKAPITULASI CAPAIAN WILAYAH BINAAN PETUGAS LAPANGAN KB</h4>
            <p class="text-[10px] text-slate-600 mt-0.5">
                Wilayah Tugas: {{ $currentUser->desa ? 'Desa/Kel. ' . $currentUser->desa->name : ($currentUser->kecamatan ? 'Kec. ' . $currentUser->kecamatan->name : 'Seluruh Wilayah') }} &bull; Periode: {{ $periodeBulan === 'all' ? 'Semua Bulan' : Carbon::create(null, (int)$periodeBulan, 1)->translatedFormat('F') }} {{ $periodeTahun }}
            </p>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         TOP BANNER / HEADER (Disembunyikan saat cetak)
         ════════════════════════════════════════════════════════════ -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0B2E59] via-[#0e3a6e] to-[#008060] p-6 sm:p-8 text-white shadow-xl print:hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-emerald-300 text-xs font-semibold border border-white/15">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Laporan &amp; Monitoring Wilayah Binaan</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Rekapitulasi Capaian Wilayah Binaan
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Evaluasi komprehensif cakupan skrining risiko 5 indikator, pemetaan sasaran PUS MUPAR, dan realisasi intervensi KB untuk pertanggungjawaban PKB/PLKB di Kabupaten Bangka.
                </p>
            </div>

            <!-- Export & Print Actions -->
            <div class="flex items-center gap-3">
                <button onclick="window.print()"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-xs transition shadow-sm">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Cetak Lembar Kerja</span>
                </button>

                <button wire:click="exportCsv"
                        type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#008060] hover:bg-[#006e52] text-white font-bold text-xs shadow-lg shadow-[#008060]/30 transition transform active:scale-95 data-loading:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Unduh CSV</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         FILTER BAR (Tahun, Bulan, Desa)
         ════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl bg-white dark:bg-slate-800 p-5 shadow-xs border border-slate-200 dark:border-slate-700 print:hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-[#008060] dark:text-emerald-400 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Parameter Periode &amp; Wilayah</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Saring ringkasan target berdasarkan bulan dan desa binaan</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                
                <!-- Filter Tahun -->
                <div class="w-32">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tahun</label>
                    <select wire:model.live="periodeTahun"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @foreach(range(now()->year, 2024) as $y)
                            <option value="{{ $y }}">Tahun {{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="w-36">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bulan</label>
                    <select wire:model.live="periodeBulan"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        <option value="all">Semua Bulan</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>

                <!-- Filter Desa (Jika punya akses multi-desa) -->
                @if($availableDesas->count() > 1)
                    <div class="min-w-[200px]">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Filter Desa / Kelurahan</label>
                        <select wire:model.live="selectedDesaId"
                                class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                            <option value="">-- Seluruh Desa Binaan --</option>
                            @foreach($availableDesas as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         METRIC SUMMARY CARDS
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        
        <!-- Card 1: Total PUS Binaan -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total PUS Sasaran</span>
                <div class="w-9 h-9 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-[#0B2E59] dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black text-slate-900 dark:text-white mt-3">{{ number_format($summary['totalPus']) }}</p>
            <div class="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                <span>Terdata di wilayah binaan</span>
            </div>
        </div>

        <!-- Card 2: Capaian Skrining -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diskrining 5 Indikator</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-[#008060] dark:text-emerald-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mt-3">
                <p class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalScreening']) }}</p>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400">({{ $summary['persenCakupan'] }}%)</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-[#008060] h-1.5 rounded-full transition-all" style="width: {{ min(100, $summary['persenCakupan']) }}%"></div>
            </div>
        </div>

        <!-- Card 3: Prioritas Utama (Skor 11-15 + MUPAR) -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Prioritas Utama</span>
                <div class="w-9 h-9 rounded-xl bg-purple-50 dark:bg-purple-950/50 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-3">{{ number_format($summary['prioritasUtama']) }}</p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                Skor 11–15 &amp; MUPAR <span class="text-slate-400">({{ number_format($summary['totalMupar']) }} Total MUPAR)</span>
            </p>
        </div>

        <!-- Card 4: Realisasi Intervensi -->
        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Realisasi Intervensi</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-950/50 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
            </div>
            <div class="flex items-baseline gap-2 mt-3">
                <p class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalIntervensi']) }}</p>
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400">({{ $summary['persenIntervensi'] }}%)</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Intervensi &amp; konseling KB terlaksana</p>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         TABEL REKAPITULASI DESA / KELURAHAN BINAAN
         ════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">
        
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    Matriks Capaian per Desa / Kelurahan Binaan
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Rincian target sasaran PUS, cakupan skrining, dan penyelesaian intervensi KB di wilayah kerja.
                </p>
            </div>
            <div class="text-xs text-slate-500 font-mono">
                Total: {{ count($rekapDesa) }} Desa/Kelurahan
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60 uppercase text-[10px] font-bold tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-3.5 px-4 text-center w-12">No</th>
                        <th class="py-3.5 px-4">Desa / Kelurahan</th>
                        <th class="py-3.5 px-4">Kecamatan</th>
                        <th class="py-3.5 px-4 text-center">Total PUS</th>
                        <th class="py-3.5 px-4 text-center">Diskrining</th>
                        <th class="py-3.5 px-4 text-center">Cakupan (%)</th>
                        <th class="py-3.5 px-4 text-center text-purple-600 dark:text-purple-400">Prioritas Utama</th>
                        <th class="py-3.5 px-4 text-center text-rose-600 dark:text-rose-400">Prioritas Tinggi</th>
                        <th class="py-3.5 px-4 text-center">MUPAR</th>
                        <th class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">Intervensi</th>
                        <th class="py-3.5 px-4 text-center">Capaian Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                    @forelse($rekapDesa as $idx => $r)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750/50 transition">
                            <td class="py-3 px-4 text-center font-mono text-slate-400">{{ $idx + 1 }}</td>
                            
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                {{ $r['desa_name'] }}
                                <span class="block text-[10px] font-mono text-slate-400">{{ $r['desa_code'] }}</span>
                            </td>

                            <td class="py-3 px-4 text-slate-600 dark:text-slate-400">
                                Kec. {{ $r['kecamatan_name'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold">
                                {{ number_format($r['total_pus']) }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-slate-900 dark:text-white">
                                {{ number_format($r['diskrining']) }}
                            </td>

                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 font-bold {{ $r['persen_cakupan'] >= 80 ? 'text-emerald-600' : ($r['persen_cakupan'] >= 50 ? 'text-amber-600' : 'text-slate-500') }}">
                                    <span>{{ $r['persen_cakupan'] }}%</span>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-purple-600 dark:text-purple-400">
                                {{ $r['prioritas_utama'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-rose-600 dark:text-rose-400">
                                {{ $r['prioritas_tinggi'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-semibold text-slate-700 dark:text-slate-300">
                                {{ $r['total_mupar'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $r['intervensi_selesai'] }}
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold {{ $r['persen_intervensi'] >= 75 ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : ($r['persen_intervensi'] >= 40 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}">
                                    {{ $r['persen_intervensi'] }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="py-8 text-center text-slate-400">
                                Belum ada data wilayah binaan yang tercatat.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <!-- TOTAL ROW -->
                @if(count($rekapDesa) > 0)
                    <tfoot class="bg-slate-100/90 dark:bg-slate-900 font-bold border-t-2 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white">
                        <tr>
                            <td colspan="3" class="py-3.5 px-4 text-center uppercase tracking-wider text-[11px]">
                                Total Seluruh Wilayah Binaan
                            </td>
                            <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalPus']) }}</td>
                            <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalScreening']) }}</td>
                            <td class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">{{ $summary['persenCakupan'] }}%</td>
                            <td class="py-3.5 px-4 text-center text-purple-600 dark:text-purple-400">{{ number_format($summary['prioritasUtama']) }}</td>
                            <td class="py-3.5 px-4 text-center text-rose-600 dark:text-rose-400">{{ number_format($summary['prioritasTinggi']) }}</td>
                            <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalMupar']) }}</td>
                            <td class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">{{ number_format($summary['totalIntervensi']) }}</td>
                            <td class="py-3.5 px-4 text-center">{{ $summary['persenIntervensi'] }}%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         DAFTAR PRIORITAS UTAMA BUTUH TINDAK LANJUT SEGERA
         ════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-purple-200 dark:border-purple-900/60 overflow-hidden print:hidden">
        
        <div class="p-6 bg-purple-50/50 dark:bg-purple-950/20 border-b border-purple-100 dark:border-purple-900/40 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Antrean Prioritas Utama Belum Terintervensi</span>
                        <span class="px-2 py-0.5 rounded-full bg-purple-600 text-white text-[10px] font-extrabold">{{ $urgentList->count() }} PUS</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Sasaran berisiko tinggi (Skor 11–15 &amp; Flag MUPAR) yang memerlukan kunjungan konseling segera.
                    </p>
                </div>
            </div>

            <a href="{{ route('screening.priority-queue') }}"
               wire:navigate
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold shadow transition">
                <span>Buka Antrean Prioritas</span>
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/40 uppercase text-[10px] font-bold tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-3 px-4">Nama Istri / Suami</th>
                        <th class="py-3 px-4">Wilayah</th>
                        <th class="py-3 px-4 text-center">Usia &amp; Paritas</th>
                        <th class="py-3 px-4 text-center">Skor 5 Indikator</th>
                        <th class="py-3 px-4 text-center">Status MUPAR</th>
                        <th class="py-3 px-4">Tanggal Skrining</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                    @forelse($urgentList as $item)
                        <tr class="hover:bg-purple-50/30 dark:hover:bg-purple-950/10 transition">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                {{ $item->pusProfile->nama_istri }}
                                <span class="block text-[11px] font-normal text-slate-500">Suami: {{ $item->pusProfile->nama_suami }}</span>
                            </td>

                            <td class="py-3 px-4">
                                <span>Desa {{ $item->pusProfile->desa?->name }}</span>
                                <span class="block text-[10px] text-slate-400">Kec. {{ $item->pusProfile->kecamatan?->name }}</span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="font-semibold">{{ $item->pusProfile->usiaIstri() }} thn</span>
                                <span class="block text-[10px] text-slate-400">{{ $item->pusProfile->jumlah_anak_lahir_hidup }} Anak</span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-xl bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 font-extrabold text-xs">
                                    {{ $item->total_skor }} / 15
                                </span>
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2 py-0.5 rounded-full bg-purple-600 text-white font-extrabold text-[10px]">
                                    MUPAR AKTIF
                                </span>
                            </td>

                            <td class="py-3 px-4 text-slate-500 text-[11px]">
                                {{ $item->tanggal_skrining->translatedFormat('d M Y') }}
                            </td>

                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('screening.priority-queue') }}"
                                   wire:navigate
                                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-[#008060] hover:bg-[#006e52] text-white text-[11px] font-bold shadow-xs transition">
                                    <span>Tindak Lanjut</span>
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-emerald-600 dark:text-emerald-400 font-semibold text-xs">
                                Seluruh sasaran Prioritas Utama di wilayah binaan telah selesai diintervensi!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         SIGNATURE SECTION UNTUK CETAK LEMBAR KERJA RESMI
         ════════════════════════════════════════════════════════════ -->
    <div class="hidden print:block mt-8 pt-6 border-t border-slate-300 text-xs">
        <div class="grid grid-cols-2 gap-8 text-center">
            <div>
                <p class="text-slate-500">Mengetahui,</p>
                <p class="font-bold text-slate-900 mt-1">Koordinator Balai Penyuluhan KB</p>
                <p class="text-slate-700">Kecamatan {{ $currentUser->kecamatan?->name ?? '.......................' }}</p>
                <div class="h-20"></div>
                <p class="font-bold text-slate-900 underline">(&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;)</p>
                <p class="text-[10px] text-slate-500">NIP. ........................................</p>
            </div>

            <div>
                <p class="text-slate-500">Sungailiat, {{ now()->translatedFormat('d F Y') }}</p>
                <p class="font-bold text-slate-900 mt-1">Petugas Lapangan KB (PKB / PLKB)</p>
                <p class="text-slate-700">Wilayah {{ $currentUser->desa ? 'Desa/Kel. ' . $currentUser->desa->name : 'Binaan' }}</p>
                <div class="h-20"></div>
                <p class="font-bold text-slate-900 underline">{{ $currentUser->name }}</p>
                <p class="text-[10px] text-slate-500">NIP. {{ $currentUser->nip ?? '........................................' }}</p>
            </div>
        </div>
    </div>

</div>
