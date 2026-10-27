<?php

use App\Enums\PriorityCategory;
use App\Models\AuditLog;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Services\ScreeningCalculatorService;
use Livewire\Attributes\Url;
use Livewire\Component;

new class extends Component
{
    #[Url(as: 'pus_id')]
    public ?int $pus_id = null;

    // Search PUS if not selected
    public string $pusSearch = '';

    // Selected PUS data
    public ?PusProfile $selectedPus = null;
    public int $usia_istri = 0;
    public int $jumlah_anak = 0;

    // 5 Parameters (0-3 each)
    public int $p1 = 3; // Auto calculated from usia_istri
    public int $p2 = 3; // Auto calculated from jumlah_anak
    public int $p3 = 0; // Live input
    public int $p4 = 0; // Live input
    public int $p5 = 0; // Live input

    // Flag MUPAR (boolean, strictly does NOT add to score)
    public bool $is_mupar = false;

    // Metadata
    public string $tanggal_skrining = '';
    public string $catatan = '';

    public function mount(ScreeningCalculatorService $calculator): void
    {
        $this->tanggal_skrining = now()->format('Y-m-d');

        if ($this->pus_id) {
            $this->selectPus($this->pus_id, $calculator);
        }
    }

    public function selectPus(int $id, ScreeningCalculatorService $calculator): void
    {
        $pus = PusProfile::with(['kecamatan', 'desa'])->forCurrentUser()->findOrFail($id);
        $this->authorize('create', [Screening::class, $pus]);

        $this->selectedPus = $pus;
        $this->pus_id = $pus->id;
        $this->usia_istri = $pus->usiaIstri();
        $this->jumlah_anak = $pus->jumlah_anak_lahir_hidup;

        // Recalculate automatic indicators P1, P2 and MUPAR flag
        $this->p1 = $calculator->calculateP1($this->usia_istri);
        $this->p2 = $calculator->calculateP2($this->jumlah_anak);
        $this->is_mupar = $calculator->determineMupar($this->usia_istri, $this->jumlah_anak);

        $this->pusSearch = '';
    }

    public function clearSelectedPus(): void
    {
        $this->selectedPus = null;
        $this->pus_id = null;
        $this->usia_istri = 0;
        $this->jumlah_anak = 0;
        $this->p1 = 0;
        $this->p2 = 0;
        $this->is_mupar = false;
    }

    public function saveScreening(ScreeningCalculatorService $calculator): void
    {
        $this->validate([
            'pus_id' => 'required|integer',
            'p3' => 'required|integer|between:0,3',
            'p4' => 'required|integer|between:0,3',
            'p5' => 'required|integer|between:0,3',
            'tanggal_skrining' => 'required|date|before_or_equal:today',
            'catatan' => 'nullable|string|max:1000',
        ], [
            'pus_id.required' => 'Pilih profil PUS yang akan diskrining terlebih dahulu.',
            'p3.required' => 'Parameter P3 wajib dipilih.',
            'p4.required' => 'Parameter P4 wajib dipilih.',
            'p5.required' => 'Parameter P5 wajib dipilih.',
            'tanggal_skrining.required' => 'Tanggal skrining wajib diisi.',
            'tanggal_skrining.before_or_equal' => 'Tanggal skrining tidak boleh di masa mendatang.',
        ]);

        // Always fetch from database scoped to current user's territory (anti-bypass)
        $pus = PusProfile::with(['kecamatan', 'desa'])->forCurrentUser()->findOrFail($this->pus_id);
        $this->authorize('create', [Screening::class, $pus]);

        // Recalculate strictly from server-side PUS database record
        $usiaIstri = $pus->usiaIstri();
        $jumlahAnak = (int) $pus->jumlah_anak_lahir_hidup;

        // Run full calculation pipeline
        $calc = $calculator->calculate(
            $usiaIstri,
            $jumlahAnak,
            (int) $this->p3,
            (int) $this->p4,
            (int) $this->p5,
        );

        $userId = auth()->id();
        if (! $userId) {
            abort(403, 'Akses tidak diizinkan. Silakan login terlebih dahulu.');
        }

        $screening = Screening::create([
            'pus_profile_id' => $pus->id,
            'user_id' => $userId,
            'p1_usia_istri' => $calc['p1_usia_istri'],
            'p2_jumlah_anak' => $calc['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => (int) $this->p3,
            'p4_tujuan_reproduksi' => (int) $this->p4,
            'p5_risiko_kehamilan' => (int) $this->p5,
            'total_skor' => $calc['total_skor'],
            'kategori' => $calc['kategori']->value,
            'is_mupar' => $calc['is_mupar'],
            'prioritas_akhir' => $calc['prioritas_akhir'],
            'urutan_antrean' => $calc['urutan_antrean'],
            'tanggal_skrining' => $this->tanggal_skrining,
            'catatan' => $this->catatan ?: null,
        ]);

        AuditLog::log(
            'create_screening',
            "Melakukan skrining 5 indikator untuk Ny. {$pus->nama_istri} (Skor: {$screening->total_skor}/15 - {$screening->prioritas_akhir})",
            $screening,
            null,
            $screening->only(['pus_profile_id', 'total_skor', 'kategori', 'is_mupar', 'prioritas_akhir', 'tanggal_skrining'])
        );

        session()->flash('success', "Skrining Ny. {$pus->nama_istri} berhasil disimpan! Skor: {$screening->total_skor}/15 ({$screening->prioritas_akhir}).");

        $this->redirect('/screening/priority-queue', navigate: true);
    }

    public function with(): array
    {
        $calculator = app(ScreeningCalculatorService::class);

        // Search candidate PUS if needed
        $pusCandidates = collect();
        if (! $this->selectedPus && strlen($this->pusSearch) >= 2) {
            $term = '%' . $this->pusSearch . '%';
            $pusCandidates = PusProfile::query()
                ->forCurrentUser()
                ->with(['kecamatan', 'desa'])
                ->where(function ($q) use ($term) {
                    $q->where('nama_istri', 'like', $term)
                      ->orWhere('nama_suami', 'like', $term)
                      ->orWhere('alamat', 'like', $term);
                })
                ->limit(8)
                ->get();
        }

        // Real-time calculations for Sticky Card
        $p1 = $this->selectedPus ? $calculator->calculateP1($this->usia_istri) : $this->p1;
        $p2 = $this->selectedPus ? $calculator->calculateP2($this->jumlah_anak) : $this->p2;
        $isMupar = $this->selectedPus ? $calculator->determineMupar($this->usia_istri, $this->jumlah_anak) : $this->is_mupar;

        $totalSkor = $calculator->calculateTotalScore($p1, $p2, $this->p3, $this->p4, $this->p5);
        $kategori = $calculator->determineCategory($totalSkor);
        $finalPriority = $kategori->finalPriority($isMupar);

        return [
            'calculator' => $calculator,
            'pusCandidates' => $pusCandidates,
            'computed_p1' => $p1,
            'computed_p2' => $p2,
            'computed_is_mupar' => $isMupar,
            'total_skor' => $totalSkor,
            'kategori' => $kategori,
            'prioritas_label' => $finalPriority['label'],
            'urutan_antrean' => $finalPriority['queue_order'],
        ];
    }
};
?>

<div>
    <!-- Breadcrumb & Title -->
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <a href="/pus" wire:navigate class="text-xs font-semibold text-slate-500 hover:text-[#008060] transition">
                    Data PUS
                </a>
                <span class="text-xs text-slate-400">/</span>
                <span class="text-xs font-semibold text-[#008060] dark:text-emerald-400">
                    Kalkulator Skrining 5 Indikator
                </span>
            </div>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 dark:text-white">
                Skrining Lima Indikator (SI-MENTILIN)
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Kalkulasi otomatis P1 &amp; P2, penentuan real-time flag MUPAR, dan perolehan skor total 0–15.
            </p>
        </div>

        <a href="/screening/priority-queue" wire:navigate
           class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:border-slate-700 shadow-xs transition">
            <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Lihat Antrean Prioritas
        </a>
    </div>

    <!-- 2-Column TailAdmin Layout (8 Kolom Input Form + 4 Kolom Sticky Score Card) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- ════════════════════════════════════════════════════════════
             KOLOM KIRI (8 Kolom): Input Form & 5 Parameter
             ════════════════════════════════════════════════════════════ -->
        <div class="lg:col-span-8 space-y-6">

            <!-- CARD 1: Pemilihan Profil PUS -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            1
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Sasaran Pasangan Usia Subur (PUS)
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Profil PUS yang sedang diwawancarai / diidentifikasi
                            </p>
                        </div>
                    </div>

                    @if($selectedPus)
                        <button wire:click="clearSelectedPus"
                                type="button"
                                class="text-xs font-semibold text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 transition">
                            Ganti Sasaran PUS
                        </button>
                    @endif
                </div>

                @if($selectedPus)
                    <!-- Active PUS Profile Card -->
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 rounded-2xl bg-[#008060] text-white flex items-center justify-center font-bold text-base shadow-md shadow-[#008060]/20">
                                {{ strtoupper(substr($selectedPus->nama_istri, 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                    Ny. {{ $selectedPus->nama_istri }}
                                    <span class="text-xs font-normal text-slate-500">/ Tn. {{ $selectedPus->nama_suami }}</span>
                                </h4>
                                <div class="flex flex-wrap items-center gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    <span>Tgl Lahir: {{ $selectedPus->tanggal_lahir_istri->format('d M Y') }} (<strong>{{ $usia_istri }} th</strong>)</span>
                                    <span>&bull;</span>
                                    <span>Anak Hidup: <strong>{{ $jumlah_anak }} anak</strong></span>
                                    <span>&bull;</span>
                                    <span>{{ $selectedPus->desa?->name }}, Kec. {{ $selectedPus->kecamatan?->name }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            @if($computed_is_mupar)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-100 text-purple-900 border border-purple-300 dark:bg-purple-950/80 dark:text-purple-300 dark:border-purple-800 shadow-xs">
                                    <svg class="w-3.5 h-3.5 text-purple-700 dark:text-purple-300" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/>
                                    </svg>
                                    PUS MUPAR (Target Khusus)
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                    Non-MUPAR
                                </span>
                            @endif
                        </div>
                    </div>
                @else
                    <!-- Search Input to Select PUS -->
                    <div class="space-y-3">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                            Cari Pasangan Usia Subur (Ketik Min. 2 Karakter)
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input wire:model.live.debounce.300ms="pusSearch"
                                   type="text"
                                   placeholder="Ketik nama istri, suami, atau alamat..."
                                   class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-transparent transition">
                        </div>

                        <!-- Dropdown Search Results -->
                        @if($pusCandidates->isNotEmpty())
                            <div class="p-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-lg divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach($pusCandidates as $cand)
                                    <button wire:click="selectPus({{ $cand->id }})"
                                            type="button"
                                            class="w-full p-2.5 text-left rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 flex items-center justify-between transition">
                                        <div>
                                            <p class="text-xs font-bold text-slate-900 dark:text-white">
                                                Ny. {{ $cand->nama_istri }} / Tn. {{ $cand->nama_suami }}
                                            </p>
                                            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                                Usia: {{ $cand->usiaIstri() }} th &bull; Anak: {{ $cand->jumlah_anak_lahir_hidup }} &bull; {{ $cand->desa?->name }}, Kec. {{ $cand->kecamatan?->name }}
                                            </p>
                                        </div>
                                        <span class="text-xs font-semibold text-[#008060] dark:text-emerald-400">
                                            Pilih &rarr;
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(strlen($pusSearch) >= 2)
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 text-center text-xs text-slate-500 dark:text-slate-400">
                                Tidak ada profil PUS yang cocok dengan pencarian "{{ $pusSearch }}".
                                <a href="/pus" wire:navigate class="ml-1 font-semibold text-[#008060] hover:underline">
                                    Daftarkan PUS Baru
                                </a>
                            </div>
                        @else
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                Silakan cari nama atau pilih dari <a href="/pus" wire:navigate class="text-[#008060] font-medium hover:underline">Daftar Profil PUS</a>.
                            </p>
                        @endif

                        @error('pus_id')
                            <p class="mt-1 text-xs text-red-600 font-semibold">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>

            <!-- CARD 2: Parameter Otomatis P1 & P2 (Read-only + Penjelasan Skor) -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            2
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                Indikator Terkalkulasi Otomatis (P1 &amp; P2)
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Dihitung langsung dari data profil PUS tanpa input manual petugas
                            </p>
                        </div>
                    </div>

                    <span class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-100 text-[#008060] dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                        Otomatis Sistem
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- P1 Usia Istri -->
                    <div class="p-4 rounded-xl border {{ $selectedPus ? 'bg-slate-50/70 border-slate-200 dark:bg-slate-900/60 dark:border-slate-700' : 'bg-slate-50 border-dashed border-slate-300 dark:bg-slate-900/30 dark:border-slate-700' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                P1: Usia Istri
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-[#008060] text-white">
                                Skor: {{ $computed_p1 }} / 3
                            </span>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                            @if($selectedPus)
                                {{ $usia_istri }} Tahun
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400 ml-1">
                                    ({{ $usia_istri <= 35 ? '≤ 35 tahun' : ($usia_istri <= 39 ? '36–39 tahun' : ($usia_istri <= 44 ? '40–44 tahun' : '≥ 45 tahun')) }})
                                </span>
                            @else
                                <span class="text-slate-400 italic font-normal text-xs">Pilih PUS terlebih dahulu</span>
                            @endif
                        </div>
                        <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Aturan: ≤35 th (Skor 3) &bull; 36–39 th (Skor 2) &bull; 40–44 th (Skor 1) &bull; ≥45 th (Skor 0).
                        </div>
                    </div>

                    <!-- P2 Jumlah Anak Lahir Hidup -->
                    <div class="p-4 rounded-xl border {{ $selectedPus ? 'bg-slate-50/70 border-slate-200 dark:bg-slate-900/60 dark:border-slate-700' : 'bg-slate-50 border-dashed border-slate-300 dark:bg-slate-900/30 dark:border-slate-700' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                                P2: Anak Lahir Hidup
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-[#008060] text-white">
                                Skor: {{ $computed_p2 }} / 3
                            </span>
                        </div>
                        <div class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">
                            @if($selectedPus)
                                {{ $jumlah_anak }} Anak
                                <span class="text-xs font-normal text-slate-500 dark:text-slate-400 ml-1">
                                    ({{ $jumlah_anak <= 2 ? '0–2 anak' : ($jumlah_anak == 3 ? '3 anak' : ($jumlah_anak == 4 ? '4 anak' : '≥ 5 anak')) }})
                                </span>
                            @else
                                <span class="text-slate-400 italic font-normal text-xs">Pilih PUS terlebih dahulu</span>
                            @endif
                        </div>
                        <div class="mt-2 text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                            Aturan: 0–2 anak (Skor 3) &bull; 3 anak (Skor 2) &bull; 4 anak (Skor 1) &bull; ≥5 anak (Skor 0).
                        </div>
                    </div>
                </div>

                <!-- Info Box Posisi Strategis MUPAR -->
                <div class="mt-4 p-3.5 rounded-xl bg-purple-50 dark:bg-purple-950/30 border border-purple-200 dark:border-purple-900/50 flex items-start gap-3">
                    <svg class="w-5 h-5 text-purple-700 dark:text-purple-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-xs text-purple-900 dark:text-purple-300 leading-relaxed">
                        <strong class="font-bold">Ketentuan MUPAR (Muda Paritas Rendah):</strong>
                        MUPAR aktif jika Usia Istri ≤ 35 tahun <em>DAN</em> Anak Hidup ≤ 2.
                        <span class="font-bold text-purple-950 dark:text-purple-200 underline">MUPAR adalah penanda prioritas intervensi dan BUKAN parameter penambah angka skor</span> (Total skor murni P1 s/d P5, rentang 0–15).
                    </div>
                </div>
            </div>

            <!-- CARD 3: Parameter P3 (Status Kontrasepsi Sesuai Tujuan) -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            3
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                P3: Status Penggunaan Kontrasepsi Sesuai Tujuan Reproduksi
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Kesesuaian dan konsistensi metode KB yang sedang digunakan
                            </p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                        Skor P3: {{ $p3 }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $p3Options = [
                            0 => ['title' => 'Konsisten & Sesuai Tujuan', 'desc' => 'Menggunakan metode kontrasepsi modern secara teratur dan selaras dengan rencana keluarga.'],
                            1 => ['title' => 'Ada Pertanyaan / Khawatir Ringan', 'desc' => 'Menggunakan KB namun terdapat keluhan ringan, keraguan, atau informasi yang belum jelas.'],
                            2 => ['title' => 'Tidak Konsisten / Kurang Sesuai', 'desc' => 'Pemakaian sering terputus, lupa minum pil, atau metode tidak sesuai jangka waktu tunda.'],
                            3 => ['title' => 'Tidak Ber-KB (Unmet Need)', 'desc' => 'Tidak menggunakan kontrasepsi sama sekali padahal tidak menginginkan kehamilan.'],
                        ];
                    @endphp

                    @foreach($p3Options as $score => $opt)
                        <label wire:key="p3-opt-{{ $score }}"
                               class="relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $p3 === $score ? 'bg-[#008060]/10 border-[#008060] ring-2 ring-[#008060]/30 dark:bg-emerald-950/40 dark:border-emerald-500' : 'bg-slate-50/50 hover:bg-slate-100/60 border-slate-200 dark:bg-slate-900/40 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                            <input type="radio" wire:model.live="p3" value="{{ $score }}" class="mt-1 text-[#008060] focus:ring-[#008060]">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $opt['title'] }}
                                    </span>
                                    <span class="text-[11px] font-extrabold px-1.5 py-0.5 rounded {{ $score >= 2 ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                        +{{ $score }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                                    {{ $opt['desc'] }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('p3') <p class="mt-2 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
            </div>

            <!-- CARD 4: Parameter P4 (Tujuan Reproduksi Saat Ini) -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            4
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                P4: Keinginan / Tujuan Reproduksi Saat Ini
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Rencana penambahan keturunan dan jarak kehamilan pasangan
                            </p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                        Skor P4: {{ $p4 }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $p4Options = [
                            0 => ['title' => 'Merencanakan Kehamilan', 'desc' => 'Sedang aktif mendambakan atau merencanakan kehamilan dalam waktu dekat.'],
                            1 => ['title' => 'Belum Rencana / Masih Terbuka', 'desc' => 'Belum merencanakan kehamilan khusus, namun siap jika terjadi kehamilan.'],
                            2 => ['title' => 'Ingin Menunda Kehamilan', 'desc' => 'Ingin menjarangkan kelahiran anak berikutnya dalam rentang 2–3 tahun ke depan.'],
                            3 => ['title' => 'Menunda Jangka Panjang / Stop', 'desc' => 'Ingin menunda lama / menyudahi keturunan tapi belum memakai metode MKJP.'],
                        ];
                    @endphp

                    @foreach($p4Options as $score => $opt)
                        <label wire:key="p4-opt-{{ $score }}"
                               class="relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $p4 === $score ? 'bg-[#008060]/10 border-[#008060] ring-2 ring-[#008060]/30 dark:bg-emerald-950/40 dark:border-emerald-500' : 'bg-slate-50/50 hover:bg-slate-100/60 border-slate-200 dark:bg-slate-900/40 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                            <input type="radio" wire:model.live="p4" value="{{ $score }}" class="mt-1 text-[#008060] focus:ring-[#008060]">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $opt['title'] }}
                                    </span>
                                    <span class="text-[11px] font-extrabold px-1.5 py-0.5 rounded {{ $score >= 2 ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                        +{{ $score }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                                    {{ $opt['desc'] }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('p4') <p class="mt-2 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
            </div>

            <!-- CARD 5: Parameter P5 (Risiko Kehamilan Tidak Direncanakan) -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100 dark:border-slate-700/60">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-[#008060]/10 text-[#008060] dark:bg-emerald-500/20 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
                            5
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                P5: Risiko Kehamilan Tidak Direncanakan (KTD)
                            </h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">
                                Tingkat kerentanan kehamilan yang tidak diharapkan terjadi
                            </p>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                        Skor P5: {{ $p5 }}
                    </span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @php
                        $p5Options = [
                            0 => ['title' => 'Risiko Sangat Rendah', 'desc' => 'Kontrasepsi konsisten & tepat, perlindungan optimal, risiko KTD nihil.'],
                            1 => ['title' => 'Ada Potensi Risiko Ringan', 'desc' => 'Ada kendala suplai kontrasepsi sesekali atau efek samping yang mengganggu konsistensi.'],
                            2 => ['title' => 'Penggunaan Tidak Konsisten', 'desc' => 'Kerap lupa atau ada jeda tanpa perlindungan kontrasepsi saat berhubungan.'],
                            3 => ['title' => 'Risiko Tinggi (Aktif & Tanpa KB)', 'desc' => 'Aktif seksual, tidak ber-KB sama sekali, dan menolak kehamilan saat ini.'],
                        ];
                    @endphp

                    @foreach($p5Options as $score => $opt)
                        <label wire:key="p5-opt-{{ $score }}"
                               class="relative flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $p5 === $score ? 'bg-[#008060]/10 border-[#008060] ring-2 ring-[#008060]/30 dark:bg-emerald-950/40 dark:border-emerald-500' : 'bg-slate-50/50 hover:bg-slate-100/60 border-slate-200 dark:bg-slate-900/40 dark:border-slate-700 dark:hover:bg-slate-800' }}">
                            <input type="radio" wire:model.live="p5" value="{{ $score }}" class="mt-1 text-[#008060] focus:ring-[#008060]">
                            <div class="flex-1">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                        {{ $opt['title'] }}
                                    </span>
                                    <span class="text-[11px] font-extrabold px-1.5 py-0.5 rounded {{ $score >= 2 ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                        +{{ $score }}
                                    </span>
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400 leading-normal">
                                    {{ $opt['desc'] }}
                                </p>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('p5') <p class="mt-2 text-xs text-red-600 font-semibold">{{ $message }}</p> @enderror
            </div>

            <!-- CARD 6: Tanggal Wawancara & Catatan Lapangan -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Tanggal Wawancara Skrining <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="tanggal_skrining"
                               type="date"
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                        @error('tanggal_skrining') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                            Catatan Khusus Lapangan / Keluhan
                        </label>
                        <input wire:model="catatan"
                               type="text"
                               placeholder="Contoh: Takut jarum suntik, riwayat pusing pada pil kombinasi..."
                               class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060]">
                    </div>
                </div>
            </div>

        </div>

        <!-- ════════════════════════════════════════════════════════════
             KOLOM KANAN (4 Kolom): STICKY REAL-TIME SCORE CARD
             ════════════════════════════════════════════════════════════ -->
        <div class="lg:col-span-4 sticky top-24 space-y-4">

            <div class="p-6 rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-xl overflow-hidden relative">
                
                <!-- Background Gradient Accent -->
                <div class="absolute top-0 inset-x-0 h-2.5 {{ $total_skor >= 11 ? 'bg-red-500' : ($total_skor >= 7 ? 'bg-amber-500' : 'bg-emerald-500') }}"></div>

                <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-700/60">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Hasil Kalkulasi Real-time
                    </span>
                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-[#008060] dark:text-emerald-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        Live Scoring
                    </span>
                </div>

                <!-- Big Score Highlight Display (0 - 15) -->
                <div class="py-6 text-center">
                    <div class="inline-flex flex-col items-center justify-center w-32 h-32 rounded-full border-4 shadow-inner {{ $total_skor >= 11 ? 'border-red-500 bg-red-50 text-red-600 dark:bg-red-950/40 dark:text-red-400' : ($total_skor >= 7 ? 'border-amber-500 bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400' : 'border-emerald-500 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400') }}">
                        <span class="text-4xl font-black tracking-tight leading-none">
                            {{ $total_skor }}
                        </span>
                        <span class="text-[11px] font-bold uppercase tracking-wider mt-1 opacity-70">
                            dari 15
                        </span>
                    </div>

                    <!-- Kategori Badge -->
                    <div class="mt-4">
                        @php
                            $catStyle = match($kategori) {
                                PriorityCategory::TINGGI => 'bg-red-100 text-red-800 border-red-500 dark:bg-red-950 dark:text-red-300',
                                PriorityCategory::SEDANG => 'bg-amber-100 text-amber-800 border-amber-500 dark:bg-amber-950 dark:text-amber-300',
                                PriorityCategory::RENDAH => 'bg-emerald-100 text-emerald-800 border-emerald-500 dark:bg-emerald-950 dark:text-emerald-300',
                            };
                        @endphp
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider border {{ $catStyle }}">
                            Kategori Skor: {{ $kategori->label() }}
                        </span>
                    </div>
                </div>

                <!-- Flag MUPAR Strategic Position -->
                <div class="mb-5 p-3.5 rounded-2xl border transition {{ $computed_is_mupar ? 'bg-purple-100/80 border-purple-400 text-purple-900 dark:bg-purple-950/70 dark:text-purple-200 dark:border-purple-700' : 'bg-slate-50 border-slate-200 text-slate-600 dark:bg-slate-900/60 dark:border-slate-700 dark:text-slate-400' }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider">
                            Status MUPAR
                        </span>
                        @if($computed_is_mupar)
                            <span class="px-2 py-0.5 rounded-md text-[11px] font-black bg-purple-700 text-white shadow-xs">
                                YA (MUPAR)
                            </span>
                        @else
                            <span class="text-xs font-semibold text-slate-500">
                                BUKAN MUPAR
                            </span>
                        @endif
                    </div>
                    <p class="mt-1 text-[11px] leading-tight opacity-80">
                        {{ $computed_is_mupar ? 'Muda Paritas Rendah: Berstatus sasaran intervensi edukasi prioritas!' : 'Kriteria MUPAR tidak terpenuhi (Usia > 35 th atau Anak > 2).' }}
                    </p>
                </div>

                <!-- Final Action & Queue Order Matrix Box -->
                <div class="p-4 rounded-2xl bg-slate-900 text-white shadow-lg space-y-2">
                    <div class="flex items-center justify-between text-xs text-slate-400">
                        <span>Urutan Antrean Lapangan:</span>
                        <span class="font-bold text-amber-400">
                            #{{ $urutan_antrean }} dari 5 Tingkat
                        </span>
                    </div>

                    <div class="text-base font-extrabold text-white tracking-tight flex items-center gap-2">
                        @if($urutan_antrean === 1)
                            <span class="w-3 h-3 rounded-full bg-red-500 animate-ping"></span>
                        @endif
                        {{ $prioritas_label }}
                    </div>

                    <p class="text-xs text-slate-300 leading-snug pt-1 border-t border-white/10">
                        @if($urutan_antrean === 1)
                            <strong>Tindakan Segera:</strong> Prioritas utama penjangkauan dan konseling segera oleh PKB/PLKB.
                        @elseif($urutan_antrean === 2)
                            <strong>Tindakan:</strong> Prioritas penjangkauan dan konseling segera ke kediaman PUS.
                        @elseif($urutan_antrean === 3)
                            <strong>Tindakan:</strong> Penjangkauan dan konseling edukasi terjadwal (Fokus MUPAR).
                        @elseif($urutan_antrean === 4)
                            <strong>Tindakan:</strong> Penjangkauan dan konseling terjadwal reguler.
                        @else
                            <strong>Tindakan:</strong> Monitoring dan edukasi rutin berkala.
                        @endif
                    </p>
                </div>

                <!-- 5 Parameters Breakdown Details -->
                <div class="mt-5 space-y-2 text-xs">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        Rincian 5 Indikator:
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">P1 (Usia Istri)</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $computed_p1 }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">P2 (Anak Lahir Hidup)</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $computed_p2 }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">P3 (Status Kontrasepsi)</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $p3 }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">P4 (Tujuan Reproduksi)</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $p4 }}</span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-100 dark:border-slate-700/60">
                        <span class="text-slate-600 dark:text-slate-400">P5 (Risiko KTD)</span>
                        <span class="font-bold text-slate-900 dark:text-white">{{ $p5 }}</span>
                    </div>
                    <div class="flex justify-between pt-1 font-bold text-slate-900 dark:text-white">
                        <span>Total Skor Lima Indikator</span>
                        <span class="text-sm text-[#008060] dark:text-emerald-400">{{ $total_skor }} / 15</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-700/60">
                    <button wire:click="saveScreening"
                            type="button"
                            class="w-full py-3.5 px-4 rounded-2xl text-sm font-bold text-white bg-[#008060] hover:bg-[#006e52] shadow-lg shadow-[#008060]/30 transition transform active:scale-98 flex items-center justify-center gap-2 data-loading:opacity-50 data-loading:pointer-events-none">
                        <svg wire:loading.remove wire:target="saveScreening" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                        </svg>
                        <span wire:loading.remove wire:target="saveScreening">Simpan Hasil Skrining</span>
                        <span wire:loading wire:target="saveScreening">Menyimpan Skrining...</span>
                    </button>
                    <p class="mt-2 text-center text-[10px] text-slate-400">
                        Data otomatis tercatat ke antrean prioritas penjangkauan harian.
                    </p>
                </div>

            </div>

        </div>

    </div>
</div>