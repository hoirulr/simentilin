<?php

use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use App\Services\EconomicMetricsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Response;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Ekspor Laporan Kabupaten | DP2KBP3A Kab. Bangka')] class extends Component {
    public string $selectedKecamatanId = 'all';
    public int $periodeTahun = 2026;
    public string $periodeSemester = 'all';
    public string $periodeBulan = 'all';

    public function mount(): void
    {
        $this->periodeTahun = (int) now()->year;
        $this->periodeBulan = 'all';
    }

    public function exportCsv()
    {
        $data = $this->getLaporanData();
        $timestamp = now()->format('Ymd_His');
        $filename = "laporan-kabupaten-bangka-{$this->periodeTahun}-{$timestamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Title
            fputcsv($handle, ['PEMERINTAH KABUPATEN BANGKA']);
            fputcsv($handle, ['DINAS PENGENDALIAN PENDUDUK, KB, PEMBERDAYAAN PEREMPUAN DAN PERLINDUNGAN ANAK (DP2KBP3A)']);
            fputcsv($handle, ['REKAPITULASI CAPAIAN SKRINING RISIKO KEHAMILAN & REALISASI INTERVENSI KB']);
            fputcsv($handle, ['Periode Tahun: ' . $this->periodeTahun]);
            fputcsv($handle, []);

            // Table Header
            fputcsv($handle, [
                'No',
                'Kecamatan',
                'Kode BPS',
                'Jumlah Desa',
                'Jumlah PKB/PLKB',
                'Sasaran PUS',
                'Diskrining',
                'Cakupan Skrining (%)',
                'Prioritas Utama (11-15 + MUPAR)',
                'Prioritas Tinggi',
                'Prioritas Sedang/Rendah',
                'Total PUS MUPAR',
                'Intervensi KB Selesai',
                'Capaian Intervensi (%)',
                'Total Biaya Operasional (Rp)',
            ]);

            foreach ($data['matriksKecamatan'] as $index => $row) {
                fputcsv($handle, [
                    $index + 1,
                    $row['nama'],
                    $row['kode'],
                    $row['jumlah_desa'],
                    $row['jumlah_petugas'],
                    $row['total_pus'],
                    $row['total_diskrining'],
                    $row['persen_cakupan'] . '%',
                    $row['prioritas_utama'],
                    $row['prioritas_tinggi'],
                    $row['prioritas_sedang_rendah'],
                    $row['total_mupar'],
                    $row['intervensi_selesai'],
                    $row['persen_intervensi'] . '%',
                    $row['total_biaya'],
                ]);
            }

            // Total Kabupaten
            fputcsv($handle, [
                '',
                'TOTAL KABUPATEN BANGKA',
                '19.01',
                $data['summary']['totalDesa'],
                $data['summary']['totalPetugas'],
                $data['summary']['totalPus'],
                $data['summary']['totalDiskrining'],
                $data['summary']['persenCakupan'] . '%',
                $data['summary']['prioritasUtama'],
                $data['summary']['prioritasTinggi'],
                $data['summary']['prioritasSedangRendah'],
                $data['summary']['totalMupar'],
                $data['summary']['totalIntervensi'],
                $data['summary']['persenIntervensi'] . '%',
                $data['summary']['totalBiaya'],
            ]);

            fputcsv($handle, []);
            fputcsv($handle, ['METODE KONTRASEPSI MODERN TERPILIH']);
            fputcsv($handle, ['Metode Kontrasepsi', 'Kategori', 'Jumlah Akseptor', 'Persentase (%)']);
            foreach ($data['metodeKb'] as $m) {
                fputcsv($handle, [
                    $m['name'],
                    $m['kategori'],
                    $m['count'],
                    $m['percentage'] . '%',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getLaporanData(): array
    {
        // 1. All 8 Kecamatans in Kab Bangka
        $allKecamatans = Kecamatan::withCount('desas')->orderBy('id')->get();

        $selectedKecamatanQuery = Kecamatan::withCount('desas')->orderBy('id');
        if ($this->selectedKecamatanId !== 'all') {
            $selectedKecamatanQuery->where('id', (int) $this->selectedKecamatanId);
        }
        $kecamatans = $selectedKecamatanQuery->get();

        // 2. Date Filtering Helper
        $applyDateFilter = function ($query, string $dateColumn = 'tanggal_skrining') {
            if ($this->periodeTahun) {
                $query->whereYear($dateColumn, $this->periodeTahun);
            }

            if ($this->periodeSemester === '1') {
                $query->whereMonth($dateColumn, '<=', 6);
            } elseif ($this->periodeSemester === '2') {
                $query->whereMonth($dateColumn, '>', 6);
            }

            if ($this->periodeBulan !== 'all') {
                $query->whereMonth($dateColumn, (int) $this->periodeBulan);
            }
        };

        // 3. Matriks 8 Kecamatan
        $matriksKecamatan = [];
        $sumPus = 0;
        $sumDiskrining = 0;
        $sumUtama = 0;
        $sumTinggi = 0;
        $sumSedangRendah = 0;
        $sumMupar = 0;
        $sumIntervensi = 0;
        $sumBiaya = 0.0;
        $sumPetugas = 0;
        $sumDesa = 0;

        foreach ($kecamatans as $kec) {
            $totalPus = PusProfile::where('kecamatan_id', $kec->id)->count();

            // Total petugas assigned in this kecamatan
            $petugasCount = User::where('role', 'petugas_lapangan')
                ->where('kecamatan_id', $kec->id)
                ->count();

            // Screenings in this kecamatan
            $screeningQ = Screening::whereHas('pusProfile', function ($q) use ($kec) {
                $q->where('kecamatan_id', $kec->id);
            });
            $applyDateFilter($screeningQ, 'tanggal_skrining');

            $diskrining = (clone $screeningQ)->count();
            $prioritasUtama = (clone $screeningQ)
                ->where('total_skor', '>=', 11)
                ->where('is_mupar', true)
                ->count();
            $prioritasTinggi = (clone $screeningQ)
                ->where('total_skor', '>=', 11)
                ->where('is_mupar', false)
                ->count();
            $prioritasSedangRendah = (clone $screeningQ)
                ->where('total_skor', '<', 11)
                ->count();
            $totalMupar = (clone $screeningQ)
                ->where('is_mupar', true)
                ->count();

            // Interventions in this kecamatan
            $interventionQ = Intervention::whereHas('screening.pusProfile', function ($q) use ($kec) {
                $q->where('kecamatan_id', $kec->id);
            });
            $applyDateFilter($interventionQ, 'tanggal_kunjungan');

            $intervensiSelesai = (clone $interventionQ)->count();
            $totalBiaya = (float) (clone $interventionQ)->sum('biaya_operasional');

            $persenCakupan = $totalPus > 0 ? round(($diskrining / $totalPus) * 100, 1) : 0;
            $persenIntervensi = $diskrining > 0 ? round(($intervensiSelesai / $diskrining) * 100, 1) : 0;

            $matriksKecamatan[] = [
                'id' => $kec->id,
                'nama' => $kec->name,
                'kode' => $kec->code,
                'jumlah_desa' => $kec->desas_count,
                'jumlah_petugas' => $petugasCount,
                'total_pus' => $totalPus,
                'total_diskrining' => $diskrining,
                'persen_cakupan' => $persenCakupan,
                'prioritas_utama' => $prioritasUtama,
                'prioritas_tinggi' => $prioritasTinggi,
                'prioritas_sedang_rendah' => $prioritasSedangRendah,
                'total_mupar' => $totalMupar,
                'intervensi_selesai' => $intervensiSelesai,
                'persen_intervensi' => $persenIntervensi,
                'total_biaya' => $totalBiaya,
            ];

            $sumPus += $totalPus;
            $sumDiskrining += $diskrining;
            $sumUtama += $prioritasUtama;
            $sumTinggi += $prioritasTinggi;
            $sumSedangRendah += $prioritasSedangRendah;
            $sumMupar += $totalMupar;
            $sumIntervensi += $intervensiSelesai;
            $sumBiaya += $totalBiaya;
            $sumPetugas += $petugasCount;
            $sumDesa += $kec->desas_count;
        }

        // 4. Distribution of Contraceptive Methods
        $allInterventionsQ = Intervention::query();
        if ($this->selectedKecamatanId !== 'all') {
            $allInterventionsQ->whereHas('screening.pusProfile', function ($q) {
                $q->where('kecamatan_id', (int) $this->selectedKecamatanId);
            });
        }
        $applyDateFilter($allInterventionsQ, 'tanggal_kunjungan');

        $methodCounts = (clone $allInterventionsQ)
            ->whereNotNull('metode_kb')
            ->selectRaw('metode_kb, count(*) as count')
            ->groupBy('metode_kb')
            ->pluck('count', 'metode_kb')
            ->toArray();

        $totalMethods = array_sum($methodCounts);
        $standardMethods = [
            ['name' => 'IUD / Spiral', 'key' => 'IUD', 'kategori' => 'MKJP'],
            ['name' => 'Implan / Susuk', 'key' => 'Implan', 'kategori' => 'MKJP'],
            ['name' => 'MOW (Tubektomi)', 'key' => 'MOW', 'kategori' => 'MKJP'],
            ['name' => 'MOP (Vasektomi)', 'key' => 'MOP', 'kategori' => 'MKJP'],
            ['name' => 'Suntik KB', 'key' => 'Suntik', 'kategori' => 'Non-MKJP'],
            ['name' => 'Pil KB', 'key' => 'Pil', 'kategori' => 'Non-MKJP'],
            ['name' => 'Kondom', 'key' => 'Kondom', 'kategori' => 'Non-MKJP'],
        ];

        $metodeKb = [];
        $totalMkjp = 0;
        $totalNonMkjp = 0;

        foreach ($standardMethods as $sm) {
            $count = $methodCounts[$sm['name']] ?? ($methodCounts[$sm['key']] ?? 0);
            $pct = $totalMethods > 0 ? round(($count / $totalMethods) * 100, 1) : 0;
            
            if ($sm['kategori'] === 'MKJP') {
                $totalMkjp += $count;
            } else {
                $totalNonMkjp += $count;
            }

            $metodeKb[] = [
                'name' => $sm['name'],
                'kategori' => $sm['kategori'],
                'count' => $count,
                'percentage' => $pct,
            ];
        }

        $persenMkjp = $totalMethods > 0 ? round(($totalMkjp / $totalMethods) * 100, 1) : 0;
        $persenNonMkjp = $totalMethods > 0 ? round(($totalNonMkjp / $totalMethods) * 100, 1) : 0;

        // 5. Economic & MCPR Metric estimates
        $overallCakupan = $sumPus > 0 ? round(($sumDiskrining / $sumPus) * 100, 1) : 0;
        $overallIntervensi = $sumDiskrining > 0 ? round(($sumIntervensi / $sumDiskrining) * 100, 1) : 0;
        $biayaPerSasaran = $sumIntervensi > 0 ? round($sumBiaya / $sumIntervensi, 0) : 0;
        
        // Estimasi Penghematan Inovasi SI-MENTILIN:
        // Tanpa SI-MENTILIN, biaya penjangkauan acak rata-rata Rp 120.000 per PUS dengan tingkat konversi rendah.
        // Dengan SI-MENTILIN, biaya rata-rata Rp 45.000 - Rp 60.000 per sasaran terfokus.
        $biayaBaseline = $sumIntervensi * 125000;
        $estimasiPenghematan = max(0, $biayaBaseline - $sumBiaya);

        return [
            'allKecamatans' => $allKecamatans,
            'matriksKecamatan' => $matriksKecamatan,
            'metodeKb' => $metodeKb,
            'metodeSummary' => [
                'totalMethods' => $totalMethods,
                'totalMkjp' => $totalMkjp,
                'persenMkjp' => $persenMkjp,
                'totalNonMkjp' => $totalNonMkjp,
                'persenNonMkjp' => $persenNonMkjp,
            ],
            'summary' => [
                'totalKecamatan' => count($kecamatans),
                'totalDesa' => $sumDesa,
                'totalPetugas' => $sumPetugas,
                'totalPus' => $sumPus,
                'totalDiskrining' => $sumDiskrining,
                'persenCakupan' => $overallCakupan,
                'prioritasUtama' => $sumUtama,
                'prioritasTinggi' => $sumTinggi,
                'prioritasSedangRendah' => $sumSedangRendah,
                'totalMupar' => $sumMupar,
                'totalIntervensi' => $sumIntervensi,
                'persenIntervensi' => $overallIntervensi,
                'totalBiaya' => $sumBiaya,
                'biayaPerSasaran' => $biayaPerSasaran,
                'estimasiPenghematan' => $estimasiPenghematan,
            ],
        ];
    }

    public function with(): array
    {
        return $this->getLaporanData();
    }
};
?>

<div class="space-y-6">

    <!-- ════════════════════════════════════════════════════════════
         PRINT-ONLY KOP SURAT RESMI DP2KBP3A KABUPATEN BANGKA
         ════════════════════════════════════════════════════════════ -->
    <div class="hidden print:block mb-8 border-b-2 border-slate-900 pb-4 text-center">
        <div class="flex items-center justify-center gap-4">
            <div class="text-center w-full">
                <h3 class="text-xs font-bold tracking-widest text-slate-800 uppercase">Pemerintah Kabupaten Bangka</h3>
                <h2 class="text-sm sm:text-base font-extrabold text-slate-950 uppercase leading-snug">
                    Dinas Pengendalian Penduduk, Keluarga Berencana, Pemberdayaan Perempuan dan Perlindungan Anak (DP2KBP3A)
                </h2>
                <p class="text-[10px] text-slate-600">Jl. Pemuda No. 1 Sungailiat, Kabupaten Bangka, Kepulauan Bangka Belitung &bull; Telp/Fax: (0717) 92534</p>
            </div>
        </div>
        <div class="mt-4 pt-2 border-t border-slate-800 text-center">
            <h4 class="text-xs font-black uppercase underline">
                LAPORAN EKSEKUTIF REKAPITULASI PENANGGULANGAN RISIKO KEHAMILAN &amp; CAPAIAN PROGRAM KB KABUPATEN BANGKA
            </h4>
            <p class="text-[10px] text-slate-700 mt-1">
                Tahun Evaluasi: {{ $periodeTahun }} &bull; Periode: {{ $periodeSemester === 'all' ? '1 Tahun Penuh' : 'Semester ' . $periodeSemester }} &bull; Wilayah: {{ $selectedKecamatanId === 'all' ? 'Seluruh 8 Kecamatan' : 'Kecamatan Terpilih' }}
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
                    <span>Pusat Laporan &amp; Rekapitulasi Eksekutif</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight text-white">
                    Ekspor Laporan Resmi Kabupaten Bangka
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Dokumen komprehensif capaian 8 Kecamatan se-Kabupaten Bangka. Dilengkapi matriks wilayah, sebaran metode kontrasepsi modern, kalkulasi efisiensi inovasi PIM III, dan format siap cetak kedinasan.
                </p>
            </div>

            <!-- Export & Print Action Buttons -->
            <div class="flex items-center gap-3">
                <button onclick="window.print()"
                        type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-xs transition shadow-sm">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Cetak Dokumen Resmi</span>
                </button>

                <button wire:click="exportCsv"
                        type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#008060] hover:bg-[#006e52] text-white font-bold text-xs shadow-lg shadow-[#008060]/30 transition transform active:scale-95 data-loading:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Ekspor CSV (Excel)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         FILTER CONTROLS (Tahun, Semester, Bulan, Kecamatan)
         ════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl bg-white dark:bg-slate-800 p-5 shadow-xs border border-slate-200 dark:border-slate-700 print:hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-[#0B2E59] dark:text-blue-400 flex items-center justify-center font-bold">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Cakupan Evaluasi &amp; Filter Laporan</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih rentang waktu evaluasi dan lingkup kecamatan yang ingin diekspor</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                
                <!-- Filter Kecamatan -->
                <div class="min-w-[190px]">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kecamatan</label>
                    <select wire:model.live="selectedKecamatanId"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        <option value="all">-- Seluruh 8 Kecamatan --</option>
                        @foreach($allKecamatans as $k)
                            <option value="{{ $k->id }}">Kec. {{ $k->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Tahun -->
                <div class="w-32">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tahun Anggaran</label>
                    <select wire:model.live="periodeTahun"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        @foreach(range(now()->year, 2024) as $y)
                            <option value="{{ $y }}">Tahun {{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter Semester -->
                <div class="w-36">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Semester</label>
                    <select wire:model.live="periodeSemester"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        <option value="all">Setahun Penuh</option>
                        <option value="1">Semester 1 (Jan-Jun)</option>
                        <option value="2">Semester 2 (Jul-Des)</option>
                    </select>
                </div>

                <!-- Filter Bulan -->
                <div class="w-36">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bulan Khusus</label>
                    <select wire:model.live="periodeBulan"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        <option value="all">Semua Bulan</option>
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                        @endfor
                    </select>
                </div>

            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════
         SUMMARY INDICATOR CARDS (6 INDIKATOR KABUPATEN)
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
        
        <!-- 1. Total Sasaran PUS -->
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sasaran PUS</span>
            <p class="text-xl font-black text-slate-900 dark:text-white mt-1.5">{{ number_format($summary['totalPus']) }}</p>
            <p class="text-[10px] text-slate-500 mt-1">{{ $summary['totalDesa'] }} Desa / Kelurahan</p>
        </div>

        <!-- 2. Diskrining -->
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Diskrining</span>
            <div class="flex items-baseline gap-1 mt-1.5">
                <p class="text-xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalDiskrining']) }}</p>
                <span class="text-[11px] font-bold text-emerald-600">({{ $summary['persenCakupan'] }}%)</span>
            </div>
            <p class="text-[10px] text-slate-500 mt-1">Cakupan Skrining</p>
        </div>

        <!-- 3. Prioritas Utama (Skor 11-15 + MUPAR) -->
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-900/60 shadow-xs">
            <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider block">Prioritas Utama</span>
            <p class="text-xl font-black text-purple-600 dark:text-purple-400 mt-1.5">{{ number_format($summary['prioritasUtama']) }}</p>
            <p class="text-[10px] text-slate-500 mt-1">Skor 11–15 &bull; MUPAR</p>
        </div>

        <!-- 4. Realisasi Intervensi -->
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">Intervensi Selesai</span>
            <div class="flex items-baseline gap-1 mt-1.5">
                <p class="text-xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalIntervensi']) }}</p>
                <span class="text-[11px] font-bold text-amber-600">({{ $summary['persenIntervensi'] }}%)</span>
            </div>
            <p class="text-[10px] text-slate-500 mt-1">Konseling KB PKB</p>
        </div>

        <!-- 5. Total Biaya Operasional Riil -->
        <div class="p-4 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Biaya Operasional</span>
            <p class="text-base font-black text-slate-900 dark:text-white mt-2 truncate">Rp {{ number_format($summary['totalBiaya'] / 1000000, 1) }} Jt</p>
            <p class="text-[10px] text-slate-500 mt-1">Rp {{ number_format($summary['biayaPerSasaran']) }}/PUS</p>
        </div>

        <!-- 6. Estimasi Nilai Efisiensi (PIM III) -->
        <div class="p-4 rounded-3xl bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-teal-950/40 border border-emerald-200 dark:border-emerald-800 shadow-xs">
            <span class="text-[10px] font-bold text-[#008060] dark:text-emerald-300 uppercase tracking-wider block">Efisiensi Inovasi</span>
            <p class="text-base font-black text-[#008060] dark:text-emerald-300 mt-2 truncate">Rp {{ number_format($summary['estimasiPenghematan'] / 1000000, 1) }} Jt</p>
            <p class="text-[10px] text-emerald-700 dark:text-emerald-400 mt-1">Penghematan APBD</p>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         TABEL MATRIKS REKAPITULASI 8 KECAMATAN SE-KABUPATEN BANGKA
         ════════════════════════════════════════════════════════════ -->
    <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">
        
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">
                    Matriks Rekapitulasi Capaian Antar Kecamatan
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Komparasi capaian skrining 5 indikator, sasaran prioritas risiko, dan realisasi penjangkauan KB di 8 Kecamatan.
                </p>
            </div>
            <div class="text-xs text-slate-500 font-mono">
                Wilayah: Kabupaten Bangka (8 Kecamatan)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60 uppercase text-[10px] font-bold tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-3.5 px-4 text-center w-12">No</th>
                        <th class="py-3.5 px-4">Kecamatan</th>
                        <th class="py-3.5 px-4 text-center">Desa</th>
                        <th class="py-3.5 px-4 text-center">PKB</th>
                        <th class="py-3.5 px-4 text-center">Sasaran PUS</th>
                        <th class="py-3.5 px-4 text-center">Diskrining</th>
                        <th class="py-3.5 px-4 text-center">Cakupan (%)</th>
                        <th class="py-3.5 px-4 text-center text-purple-600 dark:text-purple-400">Prioritas Utama</th>
                        <th class="py-3.5 px-4 text-center text-rose-600 dark:text-rose-400">Prioritas Tinggi</th>
                        <th class="py-3.5 px-4 text-center">MUPAR</th>
                        <th class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">Intervensi</th>
                        <th class="py-3.5 px-4 text-center">Capaian Tindakan</th>
                        <th class="py-3.5 px-4 text-right">Biaya Riil (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                    @forelse($matriksKecamatan as $idx => $row)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-750/50 transition">
                            <td class="py-3 px-4 text-center font-mono text-slate-400">{{ $idx + 1 }}</td>
                            
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                Kec. {{ $row['nama'] }}
                                <span class="block text-[10px] font-mono text-slate-400">{{ $row['kode'] }}</span>
                            </td>

                            <td class="py-3 px-4 text-center font-semibold text-slate-600 dark:text-slate-400">
                                {{ $row['jumlah_desa'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-semibold text-slate-600 dark:text-slate-400">
                                {{ $row['jumlah_petugas'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold">
                                {{ number_format($row['total_pus']) }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-slate-900 dark:text-white">
                                {{ number_format($row['total_diskrining']) }}
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="font-bold {{ $row['persen_cakupan'] >= 80 ? 'text-emerald-600' : ($row['persen_cakupan'] >= 50 ? 'text-amber-600' : 'text-slate-500') }}">
                                    {{ $row['persen_cakupan'] }}%
                                </span>
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-purple-600 dark:text-purple-400">
                                {{ $row['prioritas_utama'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-rose-600 dark:text-rose-400">
                                {{ $row['prioritas_tinggi'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-semibold text-slate-700 dark:text-slate-300">
                                {{ $row['total_mupar'] }}
                            </td>

                            <td class="py-3 px-4 text-center font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $row['intervensi_selesai'] }}
                            </td>

                            <td class="py-3 px-4 text-center">
                                <span class="inline-block px-2.5 py-1 rounded-full text-[10px] font-bold {{ $row['persen_intervensi'] >= 75 ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : ($row['persen_intervensi'] >= 40 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}">
                                    {{ $row['persen_intervensi'] }}%
                                </span>
                            </td>

                            <td class="py-3 px-4 text-right font-mono font-medium text-slate-700 dark:text-slate-300">
                                Rp {{ number_format($row['total_biaya'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="py-8 text-center text-slate-400">
                                Tidak ada data kecamatan yang dapat ditampilkan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <!-- TOTAL ROW -->
                <tfoot class="bg-slate-100/90 dark:bg-slate-900 font-bold border-t-2 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white">
                    <tr>
                        <td colspan="2" class="py-3.5 px-4 text-center uppercase tracking-wider text-[11px]">
                            TOTAL KABUPATEN BANGKA
                        </td>
                        <td class="py-3.5 px-4 text-center">{{ $summary['totalDesa'] }}</td>
                        <td class="py-3.5 px-4 text-center">{{ $summary['totalPetugas'] }}</td>
                        <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalPus']) }}</td>
                        <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalDiskrining']) }}</td>
                        <td class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">{{ $summary['persenCakupan'] }}%</td>
                        <td class="py-3.5 px-4 text-center text-purple-600 dark:text-purple-400">{{ number_format($summary['prioritasUtama']) }}</td>
                        <td class="py-3.5 px-4 text-center text-rose-600 dark:text-rose-400">{{ number_format($summary['prioritasTinggi']) }}</td>
                        <td class="py-3.5 px-4 text-center">{{ number_format($summary['totalMupar']) }}</td>
                        <td class="py-3.5 px-4 text-center text-emerald-600 dark:text-emerald-400">{{ number_format($summary['totalIntervensi']) }}</td>
                        <td class="py-3.5 px-4 text-center">{{ $summary['persenIntervensi'] }}%</td>
                        <td class="py-3.5 px-4 text-right font-mono">Rp {{ number_format($summary['totalBiaya'], 0, ',', '.') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         DISTRIBUSI PEMILIHAN METODE KB MODERN
         ════════════════════════════════════════════════════════════ -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Summary MKJP vs Non-MKJP -->
        <div class="rounded-3xl bg-white dark:bg-slate-800 p-6 shadow-xs border border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Rasio MKJP vs Non-MKJP
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Metode Kontrasepsi Jangka Panjang di Kabupaten Bangka
            </p>

            <div class="space-y-4 mt-6">
                <!-- MKJP -->
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                        <span class="text-emerald-600 dark:text-emerald-400">MKJP (IUD, Implan, MOW, MOP)</span>
                        <span class="text-slate-900 dark:text-white">{{ $metodeSummary['totalMkjp'] }} Akseptor ({{ $metodeSummary['persenMkjp'] }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-[#008060] h-2.5 rounded-full" style="width: {{ $metodeSummary['persenMkjp'] }}%"></div>
                    </div>
                </div>

                <!-- Non-MKJP -->
                <div>
                    <div class="flex items-center justify-between text-xs font-bold mb-1.5">
                        <span class="text-blue-600 dark:text-blue-400">Non-MKJP (Suntik, Pil, Kondom)</span>
                        <span class="text-slate-900 dark:text-white">{{ $metodeSummary['totalNonMkjp'] }} Akseptor ({{ $metodeSummary['persenNonMkjp'] }}%)</span>
                    </div>
                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-[#0B2E59] h-2.5 rounded-full" style="width: {{ $metodeSummary['persenNonMkjp'] }}%"></div>
                    </div>
                </div>
            </div>

            <div class="mt-6 p-4 rounded-2xl bg-emerald-50/70 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/40 text-xs text-slate-700 dark:text-slate-300">
                <p class="font-bold text-[#008060] dark:text-emerald-300">Target Efektivitas DP2KBP3A</p>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 leading-relaxed">
                    Peningkatan rasio MKJP berkontribusi langsung pada penekanan drop-out rate kontrasepsi dan percepatan penurunan angka kelahiran total (TFR).
                </p>
            </div>
        </div>

        <!-- Detail Breakdown per Metode KB -->
        <div class="lg:col-span-2 rounded-3xl bg-white dark:bg-slate-800 p-6 shadow-xs border border-slate-200 dark:border-slate-700">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                Rincian Sebaran Metode Kontrasepsi Modern Terpilih
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Data realisasi akseptor dari hasil konseling intervensi petugas lapangan di Kabupaten Bangka.
            </p>

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 uppercase text-[10px] font-bold tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-2.5 px-3">Metode Kontrasepsi</th>
                            <th class="py-2.5 px-3 text-center">Kategori</th>
                            <th class="py-2.5 px-3 text-center">Jumlah Akseptor</th>
                            <th class="py-2.5 px-3 text-center">Pangsa Pasar (%)</th>
                            <th class="py-2.5 px-3 text-left">Proporsi Visual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                        @foreach($metodeKb as $m)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-750/50 transition">
                                <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">{{ $m['name'] }}</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-extrabold {{ $m['kategori'] === 'MKJP' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' }}">
                                        {{ $m['kategori'] }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold">{{ number_format($m['count']) }}</td>
                                <td class="py-2.5 px-3 text-center font-bold text-slate-900 dark:text-white">{{ $m['percentage'] }}%</td>
                                <td class="py-2.5 px-3 w-48">
                                    <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
                                        <div class="{{ $m['kategori'] === 'MKJP' ? 'bg-[#008060]' : 'bg-[#0B2E59]' }} h-2 rounded-full" style="width: {{ $m['percentage'] }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ════════════════════════════════════════════════════════════
         SIGNATURE SECTION UNTUK CETAK DOKUMEN RESMI DP2KBP3A
         ════════════════════════════════════════════════════════════ -->
    <div class="hidden print:block mt-10 pt-6 border-t border-slate-300 text-xs">
        <div class="grid grid-cols-2 gap-12 text-center">
            <div>
                <p class="text-slate-600">Mengetahui,</p>
                <p class="font-bold text-slate-950 mt-1">Kepala Dinas DP2KBP3A</p>
                <p class="text-slate-800">Kabupaten Bangka</p>
                <div class="h-24"></div>
                <p class="font-bold text-slate-950 underline">Drs. NURHIDAYAT, M.Si</p>
                <p class="text-[10px] text-slate-600">Pembina Utama Muda / IV c</p>
                <p class="text-[10px] text-slate-600">NIP. 19680512 199403 1 005</p>
            </div>

            <div>
                <p class="text-slate-600">Sungailiat, {{ now()->translatedFormat('d F Y') }}</p>
                <p class="font-bold text-slate-950 mt-1">Kepala Bidang Pengendalian Penduduk dan KB</p>
                <p class="text-slate-800">DP2KBP3A Kabupaten Bangka</p>
                <div class="h-24"></div>
                <p class="font-bold text-slate-950 underline">H. AHMAD SYARIF, SKM, M.Kes</p>
                <p class="text-[10px] text-slate-600">Pembina / IV a</p>
                <p class="text-[10px] text-slate-600">NIP. 19750314 200003 1 002</p>
            </div>
        </div>
    </div>

</div>
