<?php

use App\Models\AuditLog;
use App\Models\Intervention;
use App\Models\Screening;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public const JENIS_TINDAKAN_OPTIONS = [
        'KIE / Konseling KB',
        'Kunjungan Rumah / Home Visit',
        'Fasilitasi Pelayanan KB di Faskes',
        'Pemberian Kontrasepsi Ulang',
        'Pendampingan Pasca Persalinan / Keguguran',
        'Advokasi Suami / Tokoh Keluarga',
        'Lainnya',
    ];

    public const METODE_KB_MODERN_OPTIONS = [
        'IUD / AKDR',
        'Implan / Susuk',
        'MOW (Tubektomi)',
        'MOP (Vasektomi)',
        'Suntik KB 3 Bulanan',
        'Suntik KB 1 Bulanan',
        'Pil KB',
        'Kondom',
    ];

    public bool $isOpen = false;
    public ?int $screeningId = null;
    public ?Screening $screening = null;

    // Form fields
    public string $tanggal_kunjungan = '';
    public string $jenis_tindakan = 'KIE / Konseling KB';
    public string $deskripsi_tindakan = '';
    public int $durasi_menit = 30;
    public float $biaya_operasional = 25000;
    public bool $menggunakan_kb_modern = false;
    public string $metode_kb = '';
    public string $catatan = '';

    public function mount(?int $screeningId = null): void
    {
        if ($screeningId) {
            $this->loadScreening($screeningId);
        }
    }

    #[On('open-intervention-modal')]
    public function openInterventionModal(int $screeningId): void
    {
        $this->loadScreening($screeningId);
        $this->resetForm();
        $this->isOpen = true;
    }

    public function loadScreening(int $screeningId): void
    {
        $this->screeningId = $screeningId;
        $this->screening = Screening::with(['pusProfile.kecamatan', 'pusProfile.desa', 'user'])
            ->forCurrentUser(auth()->user())
            ->findOrFail($screeningId);

        $this->authorize('create', [Intervention::class, $this->screening]);
        $this->tanggal_kunjungan = now()->format('Y-m-d');

        if ($this->screening && $this->screening->p3_status_kontrasepsi <= 1) {
            $this->menggunakan_kb_modern = true;
            $this->metode_kb = 'Suntik KB 3 Bulanan';
        } else {
            $this->menggunakan_kb_modern = false;
            $this->metode_kb = '';
        }
    }

    public function updatedMenggunakanKbModern(bool $value): void
    {
        if (! $value) {
            $this->metode_kb = 'Belum Ber-KB';
        } elseif (empty($this->metode_kb) || $this->metode_kb === 'Belum Ber-KB') {
            $this->metode_kb = 'Implan / Susuk';
        }
    }

    public function resetForm(): void
    {
        $this->tanggal_kunjungan = now()->format('Y-m-d');
        $this->jenis_tindakan = 'KIE / Konseling KB';
        $this->deskripsi_tindakan = '';
        $this->durasi_menit = 30;
        $this->biaya_operasional = 25000;
        $this->menggunakan_kb_modern = false;
        $this->metode_kb = '';
        $this->catatan = '';
        $this->resetValidation();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $rules = [
            'screeningId' => ['required', 'integer'],
            'tanggal_kunjungan' => ['required', 'date', 'before_or_equal:today'],
            'jenis_tindakan' => ['required', 'string', Rule::in(self::JENIS_TINDAKAN_OPTIONS)],
            'deskripsi_tindakan' => ['nullable', 'string', 'max:1000'],
            'durasi_menit' => ['required', 'integer', 'min:5', 'max:480'],
            'biaya_operasional' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'menggunakan_kb_modern' => ['boolean'],
            'metode_kb' => [
                $this->menggunakan_kb_modern ? 'required' : 'nullable',
                'string',
                $this->menggunakan_kb_modern ? Rule::in(self::METODE_KB_MODERN_OPTIONS) : 'nullable',
            ],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];

        $messages = [
            'tanggal_kunjungan.required' => 'Tanggal kunjungan intervensi wajib diisi.',
            'tanggal_kunjungan.before_or_equal' => 'Tanggal kunjungan intervensi tidak boleh di masa mendatang.',
            'durasi_menit.required' => 'Durasi kunjungan wajib diisi.',
            'durasi_menit.min' => 'Durasi kunjungan minimal 5 menit.',
            'durasi_menit.max' => 'Durasi kunjungan maksimal 480 menit (8 jam).',
            'biaya_operasional.required' => 'Biaya operasional wajib diisi.',
            'biaya_operasional.min' => 'Biaya operasional tidak boleh bernilai negatif.',
            'biaya_operasional.max' => 'Biaya operasional maksimal Rp 10.000.000 per intervensi.',
            'jenis_tindakan.required' => 'Jenis tindakan petugas wajib dipilih.',
            'jenis_tindakan.in' => 'Jenis tindakan yang dipilih tidak valid.',
            'metode_kb.required' => 'Pilihan metode kontrasepsi modern wajib diisi jika akseptor menggunakan KB modern.',
            'metode_kb.in' => 'Metode KB modern yang dipilih tidak sesuai standar BKKBN.',
        ];

        $this->validate($rules, $messages);

        $screening = Screening::with(['pusProfile.kecamatan', 'pusProfile.desa', 'user'])
            ->forCurrentUser(auth()->user())
            ->findOrFail($this->screeningId);

        $this->authorize('create', [Intervention::class, $screening]);

        $authId = auth()->id();
        if (! $authId) {
            abort(403, 'Akses tidak diizinkan. Silakan login terlebih dahulu.');
        }

        $intervention = Intervention::create([
            'screening_id' => $screening->id,
            'user_id' => $authId,
            'tanggal_kunjungan' => $this->tanggal_kunjungan,
            'jenis_tindakan' => $this->jenis_tindakan,
            'deskripsi_tindakan' => $this->deskripsi_tindakan ?: null,
            'durasi_menit' => (int) $this->durasi_menit,
            'biaya_operasional' => (float) $this->biaya_operasional,
            'menggunakan_kb_modern' => (bool) $this->menggunakan_kb_modern,
            'metode_kb' => $this->menggunakan_kb_modern
                ? ($this->metode_kb ?: 'KB Modern')
                : ($this->metode_kb ?: 'Belum Ber-KB'),
            'catatan' => $this->catatan ?: null,
        ]);

        AuditLog::log(
            'create_intervention',
            "Mencatat intervensi '{$intervention->jenis_tindakan}' untuk Ny. {$screening->pusProfile->nama_istri}",
            $intervention,
            null,
            $intervention->only(['screening_id', 'tanggal_kunjungan', 'jenis_tindakan', 'durasi_menit', 'biaya_operasional', 'menggunakan_kb_modern', 'metode_kb'])
        );

        $this->dispatch('intervention-saved', interventionId: $intervention->id);
        $this->dispatch('notify', message: 'Data intervensi & pendampingan berhasil disimpan!');

        $this->closeModal();
    }
};
?>

<div>
    @if($isOpen)
        <div class="fixed inset-0 z-999999 overflow-y-auto" role="dialog" aria-modal="true">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-xs transition-opacity"
                 wire:click="closeModal"></div>

            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0 relative z-10">
                <div class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 w-full max-w-2xl dark:bg-slate-800 border border-slate-200 dark:border-slate-700 mx-auto" style="max-width: 640px;">
                    
                    <!-- Header with TailAdmin Deep Navy Accent -->
                    <div class="px-6 py-5 bg-[#0B2E59] text-white flex items-center justify-between border-b border-[#1e4d8c]/60">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-300 flex items-center justify-center ring-1 ring-emerald-400/40">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold leading-tight">
                                    Catat Intervensi &amp; Konseling PUS
                                </h3>
                                <p class="text-xs text-slate-300 mt-0.5">
                                    SI-MENTILIN DP2KBP3A Kab. Bangka &bull; Tindakan Terukur
                                </p>
                            </div>
                        </div>

                        <button wire:click="closeModal" 
                                type="button" 
                                class="text-slate-300 hover:text-white rounded-lg p-1.5 hover:bg-white/10 transition">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Target PUS Summary Card -->
                    @if($screening)
                        <div class="px-6 py-3.5 bg-slate-50 dark:bg-slate-900/60 border-b border-slate-200 dark:border-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-slate-900 dark:text-white">
                                            {{ $screening->pusProfile->nama_istri }}
                                        </span>
                                        <span class="text-xs text-slate-500 dark:text-slate-400">
                                            ({{ $screening->pusProfile->usiaIstri() }} thn)
                                        </span>
                                        <span class="text-xs text-slate-400">&bull;</span>
                                        <span class="text-xs text-slate-600 dark:text-slate-300">
                                            Suami: {{ $screening->pusProfile->nama_suami }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                        {{ $screening->pusProfile->desa?->name ?? 'Desa' }}, Kec. {{ $screening->pusProfile->kecamatan?->name ?? 'Kecamatan' }}
                                        &bull; {{ $screening->pusProfile->jumlah_anak_lahir_hidup }} Anak
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($screening->is_mupar)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-700 dark:bg-red-950/60 dark:text-red-300 border border-red-300 dark:border-red-800">
                                            ⚡ MUPAR
                                        </span>
                                    @endif

                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                                        @if($screening->urutan_antrean === 1) bg-red-500 text-white
                                        @elseif($screening->urutan_antrean === 2) bg-orange-500 text-white
                                        @elseif($screening->urutan_antrean === 3) bg-amber-500 text-white
                                        @elseif($screening->urutan_antrean === 4) bg-yellow-500 text-slate-900
                                        @else bg-slate-500 text-white
                                        @endif">
                                        {{ $screening->prioritas_akhir }} (Skor {{ $screening->total_skor }})
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Form Body -->
                    <form wire:submit="save" class="p-6 space-y-5">
                        
                        <!-- Row 1: Tanggal Kunjungan & Jenis Tindakan -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="tanggal_kunjungan" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Tanggal Intervensi / Kunjungan <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       id="tanggal_kunjungan"
                                       wire:model="tanggal_kunjungan"
                                       class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                                @error('tanggal_kunjungan')
                                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="jenis_tindakan" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Jenis Tindakan Petugas <span class="text-red-500">*</span>
                                </label>
                                <select id="jenis_tindakan"
                                        wire:model="jenis_tindakan"
                                        class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                                    <option value="KIE / Konseling KB">KIE / Konseling KB Mandiri</option>
                                    <option value="Kunjungan Rumah / Home Visit">Kunjungan Rumah (Home Visit)</option>
                                    <option value="Fasilitasi Pelayanan KB di Faskes">Fasilitasi Pelayanan KB di Faskes/Puskesmas</option>
                                    <option value="Pemberian Kontrasepsi Ulang">Pemberian Kontrasepsi Ulang</option>
                                    <option value="Pendampingan Pasca Persalinan / Keguguran">Pendampingan KB Pasca Persalinan</option>
                                    <option value="Advokasi Suami / Tokoh Keluarga">Advokasi Suami &amp; Keluarga</option>
                                    <option value="Lainnya">Tindakan Lapangan Lainnya</option>
                                </select>
                                @error('jenis_tindakan')
                                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Row 2: Durasi Menit & Biaya Operasional (PIM III Metrik Ekonomi) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200/60 dark:border-emerald-800/40">
                            <div>
                                <label for="durasi_menit" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Durasi Kunjungan (Menit) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="number"
                                           id="durasi_menit"
                                           wire:model="durasi_menit"
                                           min="1"
                                           max="480"
                                           class="w-full px-3.5 py-2.5 pr-14 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 text-xs text-slate-400">
                                        Menit
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                    Indikator Efisiensi Waktu (PRD §3.3)
                                </p>
                                @error('durasi_menit')
                                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="biaya_operasional" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                    Biaya Operasional (Rp) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-xs text-slate-400">
                                        Rp
                                    </span>
                                    <input type="number"
                                           id="biaya_operasional"
                                           wire:model="biaya_operasional"
                                           min="0"
                                           step="1000"
                                           class="w-full pl-9 pr-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                                </div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                                    Indikator Biaya per PUS Prioritas (PRD §3.3)
                                </p>
                                @error('biaya_operasional')
                                    <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Row 3: Konversi KB Modern & Pilihan Metode -->
                        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700 space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-xs font-bold text-slate-900 dark:text-white">
                                        Akseptor Menggunakan KB Modern?
                                    </span>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                        Meningkatkan capaian MCPR Kabupaten Bangka
                                    </p>
                                </div>

                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           wire:model.live="menggunakan_kb_modern"
                                           class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#008060]"></div>
                                </label>
                            </div>

                            @if($menggunakan_kb_modern)
                                <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                                    <label for="metode_kb" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                        Pilihan Metode Kontrasepsi Modern
                                    </label>
                                    <select id="metode_kb"
                                            wire:model="metode_kb"
                                            class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                                        <option value="">-- Pilih Metode KB --</option>
                                        <optgroup label="Metode Kontrasepsi Jangka Panjang (MKJP)">
                                            <option value="IUD / AKDR">IUD / AKDR (Spiral)</option>
                                            <option value="Implan / Susuk">Implan / Susuk (1/2 Batang)</option>
                                            <option value="MOW (Tubektomi)">MOW (Tubektomi / Medis Operasi Wanita)</option>
                                            <option value="MOP (Vasektomi)">MOP (Vasektomi / Medis Operasi Pria)</option>
                                        </optgroup>
                                        <optgroup label="Non-MKJP">
                                            <option value="Suntik KB 3 Bulanan">Suntik KB 3 Bulanan</option>
                                            <option value="Suntik KB 1 Bulanan">Suntik KB 1 Bulanan</option>
                                            <option value="Pil KB">Pil KB</option>
                                            <option value="Kondom">Kondom</option>
                                        </optgroup>
                                    </select>
                                    @error('metode_kb')
                                        <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif
                        </div>

                        <!-- Row 4: Deskripsi & Catatan -->
                        <div>
                            <label for="deskripsi_tindakan" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                Deskripsi Tindakan / Materi Konseling
                            </label>
                            <textarea id="deskripsi_tindakan"
                                      wire:model="deskripsi_tindakan"
                                      rows="2"
                                      placeholder="Contoh: Memberikan edukasi tentang metode MKJP (Implan), menjelaskan efek samping dan mengatasi keraguan pasangan..."
                                      class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]"></textarea>
                            @error('deskripsi_tindakan')
                                <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="catatan" class="block text-xs font-bold text-slate-700 dark:text-slate-200 mb-1">
                                Catatan Hasil Kunjungan / Rencana Lanjutan
                            </label>
                            <input type="text"
                                   id="catatan"
                                   wire:model="catatan"
                                   placeholder="Contoh: Akseptor bersedia pasang implan di Puskesmas Sungailiat hari Selasa depan"
                                   class="w-full px-3.5 py-2.5 text-xs rounded-xl border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-700/60 text-slate-900 dark:text-white focus:ring-2 focus:ring-[#008060] focus:border-[#008060]">
                            @error('catatan')
                                <p class="text-[11px] text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Footer Actions -->
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-end gap-3">
                            <button type="button"
                                    wire:click="closeModal"
                                    class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-800 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 transition">
                                Batal
                            </button>

                            <button type="submit"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#008060] hover:bg-[#006e52] shadow-lg shadow-[#008060]/30 transition data-loading:opacity-50">
                                <span wire:loading.remove wire:target="save" class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Simpan Realisasi Intervensi
                                </span>
                                <span wire:loading wire:target="save" class="flex items-center gap-2">
                                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                    Menyimpan...
                                </span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    @endif
</div>
