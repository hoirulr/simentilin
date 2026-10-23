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
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Laporan Rekapitulasi | SI-MENTILIN')] class extends Component {

    #[Url(as: 'kec')]
    public ?int $selectedKecamatanId = null;

    public ?int $selectedDesaId = null;
    public string $periodeBulan = 'all';
    public int $periodeTahun;

    /** Filter tambahan: kategori skor (all | rendah | sedang | tinggi) */
    public string $filterKategori = 'all';

    /** Filter tambahan: status MUPAR (all | mupar | non_mupar) */
    public string $filterMupar = 'all';

    public function mount(): void
    {
        $user = auth()->user();

        if ($user && $user->desa_id) {
            $this->selectedDesaId = $user->desa_id;
        }

        $this->periodeTahun = (int) now()->year;
        $this->periodeBulan = (string) now()->month;
    }

    public function updatedSelectedKecamatanId(): void
    {
        $this->selectedDesaId = null;
    }

    /** Export CSV (Excel-compatible) */
    public function exportCsv()
    {
        $data = $this->getRekapData();
        $user = auth()->user();
        $wilayahLabel = $user->desa?->name ?? $user->kecamatan?->name ?? 'Wilayah-Binaan';
        $filename = 'rekap-' . str($wilayahLabel)->slug() . '-' . $this->periodeTahun . '-' . $this->periodeBulan . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $callback = function () use ($data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'Kecamatan',
                'Desa / Kelurahan',
                'Kode Wilayah',
                'Total PUS Sasaran',
                'PUS Diskrining',
                'PUS Blm Diskrining',
                'Cakupan Skrining (%)',
                'MUPAR',
                'MUPAR Sdh Dikunjungi',
                'MUPAR Blm Dikunjungi',
                'Non-MUPAR',
                'Non-MUPAR Sdh Ditindak',
                'Non-MUPAR Blm Ditindak',
                'Prioritas Utama (11-15+MUPAR)',
                'Prioritas Tinggi (11-15)',
                'Prioritas Sedang (7-10)',
                'Prioritas Rendah (0-6)',
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
                    $row['belum_skrining'],
                    $row['persen_cakupan'] . '%',
                    $row['total_mupar'],
                    $row['mupar_sudah'],
                    $row['mupar_belum'],
                    $row['total_non_mupar'],
                    $row['non_sudah'],
                    $row['non_belum'],
                    $row['prioritas_utama'],
                    $row['prioritas_tinggi'],
                    $row['prioritas_sedang'],
                    $row['prioritas_rendah'],
                    $row['intervensi_selesai'],
                    $row['persen_intervensi'] . '%',
                ]);
            }

            // Summary row
            $s = $data['summary'];
            fputcsv($handle, [
                '', 'TOTAL', 'SELURUH WILAYAH', '-',
                $s['totalPus'],
                $s['totalScreening'],
                $s['totalBelumSkrining'],
                $s['persenCakupan'] . '%',
                $s['totalMupar'],
                $s['muparSudah'],
                $s['muparBelum'],
                $s['totalNonMupar'],
                $s['nonSudah'],
                $s['nonBelum'],
                $s['prioritasUtama'],
                $s['prioritasTinggi'],
                $s['prioritasSedang'],
                $s['prioritasRendah'],
                $s['totalIntervensi'],
                $s['persenIntervensi'] . '%',
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function applyDateFilter($query, string $dateColumn = 'tanggal_skrining'): void
    {
        if ($this->periodeTahun) {
            $query->whereYear($dateColumn, $this->periodeTahun);
        }
        if ($this->periodeBulan !== 'all') {
            $query->whereMonth($dateColumn, (int) $this->periodeBulan);
        }
    }

    private function applyKategoriFilter($query): void
    {
        if ($this->filterKategori !== 'all') {
            $query->where('kategori', $this->filterKategori);
        }
    }

    private function applyMuparFilter($query): void
    {
        if ($this->filterMupar === 'mupar') {
            $query->where('is_mupar', true);
        } elseif ($this->filterMupar === 'non_mupar') {
            $query->where('is_mupar', false);
        }
    }

    private function getRekapData(): array
    {
        $user = auth()->user();

        // 1. Desas accessible to the current user
        $desasQuery = Desa::with('kecamatan')->orderBy('name');
        if (! $user->isAdminDinas()) {
            if ($user->desa_id) {
                $desasQuery->where('id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $desasQuery->where('kecamatan_id', $user->kecamatan_id);
            }
        } else {
            if ($this->selectedKecamatanId) {
                $desasQuery->where('kecamatan_id', $this->selectedKecamatanId);
            }
            if ($this->selectedDesaId) {
                $desasQuery->where('id', $this->selectedDesaId);
            }
        }

        $desas = $desasQuery->get();

        // 2. Aggregation per Desa
        $rekapDesa   = [];
        $sumPus      = 0;
        $sumSkrining = 0;
        $sumMupar    = 0; $sumMuparSudah = 0; $sumMuparBelum = 0;
        $sumNon      = 0; $sumNonSudah   = 0; $sumNonBelum   = 0;
        $sumUtama    = 0; $sumTinggi     = 0; $sumSedang     = 0; $sumRendah = 0;
        $sumIntervensi = 0;

        foreach ($desas as $desa) {
            $totalPus = PusProfile::where('desa_id', $desa->id)->count();

            $baseQ = Screening::whereHas('pusProfile', fn ($q) => $q->where('desa_id', $desa->id));
            $this->applyDateFilter($baseQ);
            $this->applyKategoriFilter($baseQ);
            $this->applyMuparFilter($baseQ);

            $diskrining  = (clone $baseQ)->count();
            $belumSkrining = max(0, $totalPus - (clone $baseQ)->distinct('pus_profile_id')->count('pus_profile_id'));

            $totalMupar  = (clone $baseQ)->where('is_mupar', true)->count();
            $muparSudah  = (clone $baseQ)->where('is_mupar', true)->whereHas('interventions')->count();
            $muparBelum  = $totalMupar - $muparSudah;

            $totalNon    = (clone $baseQ)->where('is_mupar', false)->count();
            $nonSudah    = (clone $baseQ)->where('is_mupar', false)->whereHas('interventions')->count();
            $nonBelum    = $totalNon - $nonSudah;

            $prioritasUtama  = (clone $baseQ)->where('total_skor', '>=', 11)->where('is_mupar', true)->count();
            $prioritasTinggi = (clone $baseQ)->where('total_skor', '>=', 11)->where('is_mupar', false)->count();
            $prioritasSedang = (clone $baseQ)->whereBetween('total_skor', [7, 10])->count();
            $prioritasRendah = (clone $baseQ)->where('total_skor', '<=', 6)->count();

            $intervensiSelesai = (clone $baseQ)->whereHas('interventions')->count();

            $persenCakupan   = $totalPus > 0 ? round(($diskrining / $totalPus) * 100, 1) : 0;
            $persenIntervensi = $diskrining > 0 ? round(($intervensiSelesai / $diskrining) * 100, 1) : 0;

            $rekapDesa[] = [
                'desa_id'          => $desa->id,
                'desa_name'        => $desa->name,
                'desa_code'        => $desa->code,
                'kecamatan_name'   => $desa->kecamatan?->name ?? '-',
                'total_pus'        => $totalPus,
                'diskrining'       => $diskrining,
                'belum_skrining'   => $belumSkrining,
                'persen_cakupan'   => $persenCakupan,
                'total_mupar'      => $totalMupar,
                'mupar_sudah'      => $muparSudah,
                'mupar_belum'      => $muparBelum,
                'total_non_mupar'  => $totalNon,
                'non_sudah'        => $nonSudah,
                'non_belum'        => $nonBelum,
                'prioritas_utama'  => $prioritasUtama,
                'prioritas_tinggi' => $prioritasTinggi,
                'prioritas_sedang' => $prioritasSedang,
                'prioritas_rendah' => $prioritasRendah,
                'intervensi_selesai' => $intervensiSelesai,
                'persen_intervensi'  => $persenIntervensi,
            ];

            $sumPus       += $totalPus;
            $sumSkrining  += $diskrining;
            $sumMupar     += $totalMupar; $sumMuparSudah += $muparSudah; $sumMuparBelum += $muparBelum;
            $sumNon       += $totalNon;   $sumNonSudah   += $nonSudah;   $sumNonBelum   += $nonBelum;
            $sumUtama     += $prioritasUtama;
            $sumTinggi    += $prioritasTinggi;
            $sumSedang    += $prioritasSedang;
            $sumRendah    += $prioritasRendah;
            $sumIntervensi += $intervensiSelesai;
        }

        // 3. Rekapitulasi per Kecamatan (for admin view)
        $rekapKecamatan = [];
        if ($user->isAdminDinas()) {
            $kecamatans = Kecamatan::orderBy('name')->get();
            foreach ($kecamatans as $kec) {
                $desaIds = $kec->desas()->pluck('id');
                $kecPus  = PusProfile::where('kecamatan_id', $kec->id)->count();
                $kecBase = Screening::whereHas('pusProfile', fn ($q) => $q->where('kecamatan_id', $kec->id));
                $this->applyDateFilter($kecBase);
                $this->applyKategoriFilter($kecBase);
                $this->applyMuparFilter($kecBase);

                $kecSkrining  = (clone $kecBase)->distinct('pus_profile_id')->count('pus_profile_id');
                $kecMupar     = (clone $kecBase)->where('is_mupar', true)->count();
                $kecMuparSdh  = (clone $kecBase)->where('is_mupar', true)->whereHas('interventions')->count();
                $kecNon       = (clone $kecBase)->where('is_mupar', false)->count();
                $kecNonSdh    = (clone $kecBase)->where('is_mupar', false)->whereHas('interventions')->count();
                $kecUtama     = (clone $kecBase)->where('total_skor', '>=', 11)->where('is_mupar', true)->count();
                $kecTinggi    = (clone $kecBase)->where('total_skor', '>=', 11)->where('is_mupar', false)->count();
                $kecIntervensi = (clone $kecBase)->whereHas('interventions')->count();

                $rekapKecamatan[] = [
                    'id'             => $kec->id,
                    'name'           => $kec->name,
                    'total_pus'      => $kecPus,
                    'diskrining'     => $kecSkrining,
                    'belum_skrining' => $kecPus - $kecSkrining,
                    'total_mupar'    => $kecMupar,
                    'mupar_sudah'    => $kecMuparSdh,
                    'mupar_belum'    => $kecMupar - $kecMuparSdh,
                    'total_non'      => $kecNon,
                    'non_sudah'      => $kecNonSdh,
                    'non_belum'      => $kecNon - $kecNonSdh,
                    'prioritas_utama' => $kecUtama,
                    'prioritas_tinggi' => $kecTinggi,
                    'intervensi'     => $kecIntervensi,
                    'persen_intervensi' => $kecSkrining > 0 ? round(($kecIntervensi / $kecSkrining) * 100, 1) : 0,
                ];
            }
        }

        // 4. Antrean Prioritas Utama belum terintervensi
        $urgentQuery = Screening::with(['pusProfile.desa', 'pusProfile.kecamatan'])
            ->forCurrentUser($user)
            ->where('total_skor', '>=', 11)
            ->where('is_mupar', true)
            ->whereDoesntHave('interventions')
            ->orderByDesc('tanggal_skrining')
            ->limit(10);
        $this->applyDateFilter($urgentQuery);
        $urgentList = $urgentQuery->get();

        $belumSkriningTotal = max(0, $sumPus - $sumSkrining);

        return [
            'rekapDesa'       => $rekapDesa,
            'rekapKecamatan'  => $rekapKecamatan,
            'urgentList'      => $urgentList,
            'summary'         => [
                'totalPus'          => $sumPus,
                'totalScreening'    => $sumSkrining,
                'totalBelumSkrining'=> $belumSkriningTotal,
                'persenCakupan'     => $sumPus > 0 ? round(($sumSkrining / $sumPus) * 100, 1) : 0,
                'totalMupar'        => $sumMupar,
                'muparSudah'        => $sumMuparSudah,
                'muparBelum'        => $sumMuparBelum,
                'totalNonMupar'     => $sumNon,
                'nonSudah'          => $sumNonSudah,
                'nonBelum'          => $sumNonBelum,
                'prioritasUtama'    => $sumUtama,
                'prioritasTinggi'   => $sumTinggi,
                'prioritasSedang'   => $sumSedang,
                'prioritasRendah'   => $sumRendah,
                'totalIntervensi'   => $sumIntervensi,
                'persenIntervensi'  => $sumSkrining > 0 ? round(($sumIntervensi / $sumSkrining) * 100, 1) : 0,
            ],
        ];
    }

    public function with(): array
    {
        $user = auth()->user();
        $rekapData = $this->getRekapData();

        $kecamatans = Kecamatan::orderBy('name')->get();

        $availableDesasQuery = Desa::orderBy('name');
        if (! $user->isAdminDinas()) {
            if ($user->desa_id) {
                $availableDesasQuery->where('id', $user->desa_id);
            } elseif ($user->kecamatan_id) {
                $availableDesasQuery->where('kecamatan_id', $user->kecamatan_id);
            }
        } elseif ($this->selectedKecamatanId) {
            $availableDesasQuery->where('kecamatan_id', $this->selectedKecamatanId);
        }
        $availableDesas = $availableDesasQuery->get();

        return array_merge($rekapData, [
            'kecamatans'     => $kecamatans,
            'availableDesas' => $availableDesas,
            'currentUser'    => $user,
        ]);
    }
};
?>

<div class="space-y-6">

    {{-- ════════════════════════════════════════════════════════════
         PRINT-ONLY FORMAL KOP SURAT
         ════════════════════════════════════════════════════════════ --}}
    <div class="hidden print:block mb-6 border-b-2 border-slate-900 pb-4 text-center">
        <div class="text-left">
            <h3 class="text-xs font-bold tracking-widest text-slate-700 uppercase">Pemerintah Kabupaten Bangka</h3>
            <h2 class="text-sm font-extrabold text-slate-900 uppercase">Dinas Pengendalian Penduduk, KB, Pemberdayaan Perempuan dan Perlindungan Anak</h2>
            <p class="text-[10px] text-slate-600">Jl. Pemuda No. 1 Sungailiat, Kabupaten Bangka, Kepulauan Bangka Belitung</p>
        </div>
        <div class="mt-4 pt-2 border-t border-slate-400 text-center">
            <h4 class="text-xs font-bold uppercase underline">LAPORAN REKAPITULASI CAPAIAN SKRINING DAN PENJANGKAUAN PUS</h4>
            <p class="text-[10px] text-slate-600 mt-0.5">
                Wilayah: {{ $currentUser->desa ? 'Desa/Kel. ' . $currentUser->desa->name : ($currentUser->kecamatan ? 'Kec. ' . $currentUser->kecamatan->name : 'Seluruh Wilayah Kab. Bangka') }}
                &bull; Periode: {{ $periodeBulan === 'all' ? 'Semua Bulan' : Carbon::create(null, (int)$periodeBulan, 1)->translatedFormat('F') }} {{ $periodeTahun }}
            </p>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         TOP BANNER
         ════════════════════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#0B2E59] via-[#0e3a6e] to-[#008060] p-6 sm:p-8 text-white shadow-xl print:hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div class="space-y-2 max-w-2xl">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md text-emerald-300 text-xs font-semibold border border-white/15">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Laporan &amp; Monitoring Wilayah Binaan</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-tight">
                    Rekapitulasi Capaian Wilayah Binaan &amp; Skrining PUS
                </h2>
                <p class="text-slate-200 text-xs sm:text-sm leading-relaxed">
                    Evaluasi komprehensif cakupan skrining 5 indikator, pemetaan sasaran PUS MUPAR/non-MUPAR, dan realisasi intervensi KB untuk PKB/PLKB Kabupaten Bangka.
                </p>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <button onclick="window.print()" type="button"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white font-semibold text-xs border border-white/20 backdrop-blur-xs transition shadow-sm">
                    <svg class="w-4 h-4 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span>Cetak / PDF</span>
                </button>

                <button wire:click="exportCsv" type="button"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-[#008060] hover:bg-[#006e52] text-white font-bold text-xs shadow-lg shadow-[#008060]/30 transition transform active:scale-95 data-loading:opacity-50">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Unduh Excel / CSV</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         FILTER BAR
         ════════════════════════════════════════════════════════════ --}}
    <div class="rounded-3xl bg-white dark:bg-slate-800 p-5 shadow-xs border border-slate-200 dark:border-slate-700 print:hidden">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-[#008060]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Parameter Filter Laporan</h3>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">

            {{-- Filter Tahun --}}
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tahun</label>
                <select wire:model.live="periodeTahun"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                    @foreach(range(now()->year, 2024) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Filter Bulan --}}
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Bulan</label>
                <select wire:model.live="periodeBulan"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                    <option value="all">Semua Bulan</option>
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}">{{ Carbon::create(null, $m, 1)->translatedFormat('F') }}</option>
                    @endfor
                </select>
            </div>

            {{-- Filter Kecamatan (admin only) --}}
            @if($currentUser->isAdminDinas())
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kecamatan</label>
                    <select wire:model.live="selectedKecamatanId"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                        <option value="">Semua Kecamatan</option>
                        @foreach($kecamatans as $kec)
                            <option value="{{ $kec->id }}">Kec. {{ $kec->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Filter Desa --}}
            @if($availableDesas->count() > 1)
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Desa / Kelurahan</label>
                    <select wire:model.live="selectedDesaId"
                            class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                        <option value="">Seluruh Desa</option>
                        @foreach($availableDesas as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Filter Kategori Skor --}}
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Kategori Skor</label>
                <select wire:model.live="filterKategori"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                    <option value="all">Semua Kategori</option>
                    <option value="tinggi">🔴 Tinggi (11–15)</option>
                    <option value="sedang">🟡 Sedang (7–10)</option>
                    <option value="rendah">🟢 Rendah (0–6)</option>
                </select>
            </div>

            {{-- Filter Status MUPAR --}}
            <div>
                <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Status MUPAR</label>
                <select wire:model.live="filterMupar"
                        class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-semibold focus:ring-2 focus:ring-[#008060] focus:border-transparent">
                    <option value="all">MUPAR &amp; Non-MUPAR</option>
                    <option value="mupar">MUPAR Saja</option>
                    <option value="non_mupar">Non-MUPAR Saja</option>
                </select>
            </div>

        </div>

        {{-- Active Filter Badges --}}
        @if($filterKategori !== 'all' || $filterMupar !== 'all' || $selectedKecamatanId || $selectedDesaId)
            <div class="flex flex-wrap gap-2 mt-3">
                @if($filterKategori !== 'all')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300 text-[11px] font-semibold">
                        Kategori: {{ match($filterKategori) { 'tinggi' => '🔴 Tinggi', 'sedang' => '🟡 Sedang', 'rendah' => '🟢 Rendah', default => '-' } }}
                        <button wire:click="$set('filterKategori', 'all')" class="ml-1 hover:text-red-600">✕</button>
                    </span>
                @endif
                @if($filterMupar !== 'all')
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300 text-[11px] font-semibold">
                        {{ $filterMupar === 'mupar' ? 'MUPAR Saja' : 'Non-MUPAR Saja' }}
                        <button wire:click="$set('filterMupar', 'all')" class="ml-1 hover:text-red-600">✕</button>
                    </span>
                @endif
                <button wire:click="$set('filterKategori', 'all'); $set('filterMupar', 'all'); $set('selectedKecamatanId', null); $set('selectedDesaId', null)"
                        class="text-[11px] font-semibold text-red-600 hover:underline">
                    Reset Semua Filter
                </button>
            </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════════════
         SUMMARY CARDS (4 Kartu)
         ════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total PUS Sasaran</span>
            <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ number_format($summary['totalPus']) }}</p>
            <p class="text-[11px] text-slate-500 mt-1">Terdaftar di wilayah</p>
        </div>

        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Diskrining 5 Indikator</span>
            <div class="flex items-baseline gap-2 mt-2">
                <p class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalScreening']) }}</p>
                <span class="text-xs font-bold text-emerald-600">({{ $summary['persenCakupan'] }}%)</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-1.5 mt-2 overflow-hidden">
                <div class="bg-[#008060] h-1.5 rounded-full" style="width: {{ min(100, $summary['persenCakupan']) }}%"></div>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-amber-300 dark:border-amber-700/60 shadow-xs">
            <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Belum Diskrining</span>
            <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ number_format($summary['totalBelumSkrining']) }}</p>
            <p class="text-[11px] text-amber-500 mt-1">Perlu segera diskrining</p>
        </div>

        <div class="p-5 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Realisasi Intervensi</span>
            <div class="flex items-baseline gap-2 mt-2">
                <p class="text-2xl font-black text-slate-900 dark:text-white">{{ number_format($summary['totalIntervensi']) }}</p>
                <span class="text-xs font-bold text-purple-600">({{ $summary['persenIntervensi'] }}%)</span>
            </div>
            <p class="text-[11px] text-slate-500 mt-1">Kunjungan / KIE terlaksana</p>
        </div>

    </div>

    {{-- ════════════════════════════════════════════════════════════
         MUPAR / NON-MUPAR SUMMARY ROW
         ════════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-800/50">
            <p class="text-[11px] font-bold text-purple-700 dark:text-purple-400 uppercase">Total MUPAR</p>
            <p class="text-xl font-black text-purple-700 dark:text-purple-400 mt-1">{{ number_format($summary['totalMupar']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/50">
            <p class="text-[11px] font-bold text-emerald-700 dark:text-emerald-400 uppercase">MUPAR Sudah Dikunjungi</p>
            <p class="text-xl font-black text-emerald-700 dark:text-emerald-400 mt-1">{{ number_format($summary['muparSudah']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50">
            <p class="text-[11px] font-bold text-red-700 dark:text-red-400 uppercase">MUPAR Belum Dikunjungi</p>
            <p class="text-xl font-black text-red-700 dark:text-red-400 mt-1">{{ number_format($summary['muparBelum']) }}</p>
        </div>
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-600/50">
            <p class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase">Total Non-MUPAR</p>
            <p class="text-xl font-black text-slate-700 dark:text-slate-300 mt-1">{{ number_format($summary['totalNonMupar']) }}</p>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         REKAP PER KECAMATAN (Admin Only)
         ════════════════════════════════════════════════════════════ --}}
    @if($currentUser->isAdminDinas() && count($rekapKecamatan) > 0)
        <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-5 border-b border-slate-200 dark:border-slate-700">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Rekapitulasi per Kecamatan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Total 8 kecamatan Kabupaten Bangka</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="bg-slate-50 dark:bg-slate-900/60 text-[10px] font-bold uppercase tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                        <tr>
                            <th class="py-3 px-4">Kecamatan</th>
                            <th class="py-3 px-3 text-center">Total PUS</th>
                            <th class="py-3 px-3 text-center">Diskrining</th>
                            <th class="py-3 px-3 text-center text-amber-600">Blm Skrining</th>
                            <th class="py-3 px-3 text-center text-purple-600 border-l border-slate-200 dark:border-slate-700">MUPAR</th>
                            <th class="py-3 px-3 text-center text-emerald-600">MUPAR Sdh</th>
                            <th class="py-3 px-3 text-center text-red-600">MUPAR Blm</th>
                            <th class="py-3 px-3 text-center border-l border-slate-200 dark:border-slate-700">Non-MUPAR</th>
                            <th class="py-3 px-3 text-center text-emerald-600">Non-M Sdh</th>
                            <th class="py-3 px-3 text-center text-red-600">Non-M Blm</th>
                            <th class="py-3 px-3 text-center text-red-700 border-l border-slate-200 dark:border-slate-700">P.Utama</th>
                            <th class="py-3 px-3 text-center text-orange-600">P.Tinggi</th>
                            <th class="py-3 px-3 text-center">Intervensi</th>
                            <th class="py-3 px-3 text-center">Capaian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @foreach($rekapKecamatan as $kec)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">Kec. {{ $kec['name'] }}</td>
                                <td class="py-3 px-3 text-center">{{ number_format($kec['total_pus']) }}</td>
                                <td class="py-3 px-3 text-center font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($kec['diskrining']) }}</td>
                                <td class="py-3 px-3 text-center">
                                    @if($kec['belum_skrining'] > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-bold text-[11px]">{{ $kec['belum_skrining'] }}</span>
                                    @else
                                        <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-purple-700 dark:text-purple-400 border-l border-slate-100 dark:border-slate-700/40">{{ number_format($kec['total_mupar']) }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-[11px]">{{ $kec['mupar_sudah'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    @if($kec['mupar_belum'] > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 font-bold text-[11px]">{{ $kec['mupar_belum'] }}</span>
                                    @else
                                        <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center border-l border-slate-100 dark:border-slate-700/40">{{ number_format($kec['total_non']) }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-[11px]">{{ $kec['non_sudah'] }}</span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    @if($kec['non_belum'] > 0)
                                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 font-bold text-[11px]">{{ $kec['non_belum'] }}</span>
                                    @else
                                        <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 text-center font-bold text-red-700 dark:text-red-400 border-l border-slate-100 dark:border-slate-700/40">{{ $kec['prioritas_utama'] }}</td>
                                <td class="py-3 px-3 text-center font-bold text-orange-600 dark:text-orange-400">{{ $kec['prioritas_tinggi'] }}</td>
                                <td class="py-3 px-3 text-center font-bold text-emerald-700 dark:text-emerald-400">{{ $kec['intervensi'] }}</td>
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $kec['persen_intervensi'] >= 75 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : ($kec['persen_intervensi'] >= 40 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300') }}">
                                        {{ $kec['persen_intervensi'] }}%
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════
         MATRIKS CAPAIAN PER DESA / KELURAHAN
         ════════════════════════════════════════════════════════════ --}}
    <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-slate-200 dark:border-slate-700 overflow-hidden">

        <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Matriks Capaian per Desa / Kelurahan</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Rincian skrining, MUPAR/non-MUPAR, dan intervensi per wilayah kerja — Total: {{ count($rekapDesa) }} Desa/Kel.
                </p>
            </div>
            @if($filterKategori !== 'all' || $filterMupar !== 'all')
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 text-[11px] font-bold border border-blue-200 dark:border-blue-800/50">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Data difilter aktif
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="bg-slate-50/80 dark:bg-slate-900/60 uppercase text-[10px] font-bold tracking-wider text-slate-500 border-b border-slate-200 dark:border-slate-700">
                    <tr>
                        <th class="py-3.5 px-4 text-center w-10">No</th>
                        <th class="py-3.5 px-4">Desa / Kelurahan</th>
                        <th class="py-3.5 px-4">Kecamatan</th>
                        <th class="py-3.5 px-3 text-center">Total PUS</th>
                        <th class="py-3.5 px-3 text-center">Diskrining</th>
                        <th class="py-3.5 px-3 text-center text-amber-600">Blm Skrining</th>
                        <th class="py-3.5 px-3 text-center border-l border-slate-200 dark:border-slate-700 text-purple-600">MUPAR</th>
                        <th class="py-3.5 px-3 text-center text-emerald-600">Sdh Kunjung</th>
                        <th class="py-3.5 px-3 text-center text-red-600">Blm Kunjung</th>
                        <th class="py-3.5 px-3 text-center border-l border-slate-200 dark:border-slate-700">Non-MUPAR</th>
                        <th class="py-3.5 px-3 text-center text-emerald-600">Sdh Ditindak</th>
                        <th class="py-3.5 px-3 text-center text-red-600">Blm Ditindak</th>
                        <th class="py-3.5 px-3 text-center border-l border-slate-200 dark:border-slate-700 text-red-700">P.Utama</th>
                        <th class="py-3.5 px-3 text-center text-orange-600">P.Tinggi</th>
                        <th class="py-3.5 px-3 text-center text-amber-600">P.Sedang</th>
                        <th class="py-3.5 px-3 text-center text-emerald-600">P.Rendah</th>
                        <th class="py-3.5 px-3 text-center border-l border-slate-200 dark:border-slate-700">Capaian</th>
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

                            <td class="py-3 px-4 text-slate-500">Kec. {{ $r['kecamatan_name'] }}</td>

                            <td class="py-3 px-3 text-center font-bold">{{ number_format($r['total_pus']) }}</td>

                            <td class="py-3 px-3 text-center font-bold text-slate-900 dark:text-white">{{ number_format($r['diskrining']) }}</td>

                            <td class="py-3 px-3 text-center">
                                @if($r['belum_skrining'] > 0)
                                    <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-bold text-[11px]">{{ $r['belum_skrining'] }}</span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                @endif
                            </td>

                            {{-- MUPAR --}}
                            <td class="py-3 px-3 text-center font-bold text-purple-700 dark:text-purple-400 border-l border-slate-100 dark:border-slate-700/40">{{ number_format($r['total_mupar']) }}</td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-[11px]">{{ $r['mupar_sudah'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($r['mupar_belum'] > 0)
                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 font-bold text-[11px]">{{ $r['mupar_belum'] }}</span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                @endif
                            </td>

                            {{-- Non-MUPAR --}}
                            <td class="py-3 px-3 text-center border-l border-slate-100 dark:border-slate-700/40">{{ number_format($r['total_non_mupar']) }}</td>
                            <td class="py-3 px-3 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-bold text-[11px]">{{ $r['non_sudah'] }}</span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                @if($r['non_belum'] > 0)
                                    <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300 font-bold text-[11px]">{{ $r['non_belum'] }}</span>
                                @else
                                    <span class="text-emerald-600 font-bold text-[11px]">✓</span>
                                @endif
                            </td>

                            {{-- Kategori Skor --}}
                            <td class="py-3 px-3 text-center font-bold text-red-700 dark:text-red-400 border-l border-slate-100 dark:border-slate-700/40">{{ $r['prioritas_utama'] }}</td>
                            <td class="py-3 px-3 text-center font-bold text-orange-600 dark:text-orange-400">{{ $r['prioritas_tinggi'] }}</td>
                            <td class="py-3 px-3 text-center font-bold text-amber-600 dark:text-amber-400">{{ $r['prioritas_sedang'] }}</td>
                            <td class="py-3 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400">{{ $r['prioritas_rendah'] }}</td>

                            <td class="py-3 px-3 text-center border-l border-slate-100 dark:border-slate-700/40">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $r['persen_intervensi'] >= 75 ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300' : ($r['persen_intervensi'] >= 40 ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300') }}">
                                    {{ $r['persen_intervensi'] }}%
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="17" class="py-8 text-center text-slate-400">Belum ada data wilayah binaan yang tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>

                @if(count($rekapDesa) > 0)
                    <tfoot class="bg-slate-100/90 dark:bg-slate-900 font-bold border-t-2 border-slate-300 dark:border-slate-600 text-slate-900 dark:text-white text-xs">
                        <tr>
                            <td colspan="3" class="py-3.5 px-4 uppercase tracking-wider text-[11px]">Total Seluruh Wilayah</td>
                            <td class="py-3.5 px-3 text-center">{{ number_format($summary['totalPus']) }}</td>
                            <td class="py-3.5 px-3 text-center">{{ number_format($summary['totalScreening']) }}</td>
                            <td class="py-3.5 px-3 text-center text-amber-600">{{ number_format($summary['totalBelumSkrining']) }}</td>
                            <td class="py-3.5 px-3 text-center text-purple-600">{{ number_format($summary['totalMupar']) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600">{{ number_format($summary['muparSudah']) }}</td>
                            <td class="py-3.5 px-3 text-center text-red-600">{{ number_format($summary['muparBelum']) }}</td>
                            <td class="py-3.5 px-3 text-center">{{ number_format($summary['totalNonMupar']) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600">{{ number_format($summary['nonSudah']) }}</td>
                            <td class="py-3.5 px-3 text-center text-red-600">{{ number_format($summary['nonBelum']) }}</td>
                            <td class="py-3.5 px-3 text-center text-red-700">{{ number_format($summary['prioritasUtama']) }}</td>
                            <td class="py-3.5 px-3 text-center text-orange-600">{{ number_format($summary['prioritasTinggi']) }}</td>
                            <td class="py-3.5 px-3 text-center text-amber-600">{{ number_format($summary['prioritasSedang']) }}</td>
                            <td class="py-3.5 px-3 text-center text-emerald-600">{{ number_format($summary['prioritasRendah']) }}</td>
                            <td class="py-3.5 px-3 text-center">{{ $summary['persenIntervensi'] }}%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         ANTREAN PRIORITAS UTAMA BELUM TERINTERVENSI
         ════════════════════════════════════════════════════════════ --}}
    <div class="rounded-3xl bg-white dark:bg-slate-800 shadow-xs border border-red-200 dark:border-red-900/60 overflow-hidden print:hidden">

        <div class="p-6 bg-red-50/50 dark:bg-red-950/20 border-b border-red-100 dark:border-red-900/40 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-300 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span>Prioritas Utama Belum Terintervensi</span>
                        <span class="px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-extrabold">{{ $urgentList->count() }} PUS</span>
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Skor 11–15 &amp; MUPAR — memerlukan kunjungan segera.</p>
                </div>
            </div>
            <a href="{{ route('screening.priority-queue') }}" wire:navigate
               class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold shadow transition">
                <span>Buka Antrean</span>
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
                        <th class="py-3 px-4">Tgl Skrining</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                    @forelse($urgentList as $item)
                        <tr class="hover:bg-red-50/30 dark:hover:bg-red-950/10 transition">
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
                                <span class="px-2.5 py-1 rounded-xl bg-red-100 text-red-800 dark:bg-red-950/60 dark:text-red-300 font-extrabold text-xs">
                                    {{ $item->total_skor }} / 15
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-full bg-red-600 text-white font-extrabold text-[10px]">MUPAR</span>
                            </td>
                            <td class="py-3 px-4 text-slate-500 text-[11px]">{{ $item->tanggal_skrining->translatedFormat('d M Y') }}</td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('screening.priority-queue') }}" wire:navigate
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
                                ✓ Seluruh sasaran Prioritas Utama di wilayah binaan telah diintervensi!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════════════
         TANDA TANGAN UNTUK CETAK (Print Only)
         ════════════════════════════════════════════════════════════ --}}
    <div class="hidden print:block mt-8 pt-6 border-t border-slate-300 text-xs">
        <div class="grid grid-cols-2 gap-8 text-center">
            <div>
                <p class="text-slate-500">Mengetahui,</p>
                <p class="font-bold text-slate-900 mt-1">Koordinator Balai Penyuluhan KB</p>
                <p class="text-slate-700">Kecamatan {{ $currentUser->kecamatan?->name ?? '.......................' }}</p>
                <div class="h-20"></div>
                <p class="font-bold text-slate-900 underline">(________________________________)</p>
                <p class="text-[10px] text-slate-500">NIP. ................................</p>
            </div>
            <div>
                <p class="text-slate-500">Sungailiat, {{ now()->translatedFormat('d F Y') }}</p>
                <p class="font-bold text-slate-900 mt-1">Petugas Lapangan KB (PKB / PLKB)</p>
                <p class="text-slate-700">Wilayah {{ $currentUser->desa ? 'Desa/Kel. ' . $currentUser->desa->name : 'Binaan' }}</p>
                <div class="h-20"></div>
                <p class="font-bold text-slate-900 underline">{{ $currentUser->name }}</p>
                <p class="text-[10px] text-slate-500">NIP. {{ $currentUser->nip ?? '................................' }}</p>
            </div>
        </div>
    </div>

</div>
