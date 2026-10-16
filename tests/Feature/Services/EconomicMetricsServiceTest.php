<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use App\Services\EconomicMetricsService;

beforeEach(function () {
    $this->service = new EconomicMetricsService;

    // Create territory
    $this->kecamatan = Kecamatan::create(['name' => 'Sungailiat', 'code' => '19.01.01']);
    $this->desa = Desa::create(['kecamatan_id' => $this->kecamatan->id, 'name' => 'Kudai', 'code' => '19.01.01.2001']);

    // Create user
    $this->user = User::factory()->create([
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
    ]);
});

// ──── Helper ──────────────────────────────────────────────

function createPusWithScreeningAndIntervention(
    object $testContext,
    int $totalSkor,
    float $biaya,
    int $durasi,
    bool $kbModern,
    ?string $tanggalKunjungan = null,
): Intervention {
    $pus = PusProfile::create([
        'user_id' => $testContext->user->id,
        'kecamatan_id' => $testContext->kecamatan->id,
        'desa_id' => $testContext->desa->id,
        'nama_istri' => fake()->name('female'),
        'nama_suami' => fake()->name('male'),
        'tanggal_lahir_istri' => now()->subYears(30),
        'jumlah_anak_lahir_hidup' => 2,
    ]);

    $screening = Screening::create([
        'pus_profile_id' => $pus->id,
        'user_id' => $testContext->user->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 1,
        'p4_tujuan_reproduksi' => 1,
        'p5_risiko_kehamilan' => 1,
        'total_skor' => $totalSkor,
        'kategori' => $totalSkor >= 11 ? 'tinggi' : ($totalSkor >= 7 ? 'sedang' : 'rendah'),
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
        'tanggal_skrining' => now(),
    ]);

    return Intervention::create([
        'screening_id' => $screening->id,
        'user_id' => $testContext->user->id,
        'tanggal_kunjungan' => $tanggalKunjungan ?? now()->toDateString(),
        'jenis_tindakan' => 'Konseling',
        'durasi_menit' => $durasi,
        'biaya_operasional' => $biaya,
        'menggunakan_kb_modern' => $kbModern,
        'metode_kb' => $kbModern ? 'IUD' : null,
    ]);
}

// ──── Ketepatan Sasaran ───────────────────────────────────

describe('ketepatanSasaran', function () {
    it('returns 0 when no interventions exist', function () {
        expect($this->service->ketepatanSasaran())->toBe(0.0);
    });

    it('returns 100 when all reached PUS are priority (score >= 7)', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 8, biaya: 30000, durasi: 20, kbModern: false);

        expect($this->service->ketepatanSasaran())->toBe(100.0);
    });

    it('returns correct percentage with mixed scores', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 4, biaya: 20000, durasi: 15, kbModern: false);

        // 1 priority out of 2 total = 50%
        expect($this->service->ketepatanSasaran())->toBe(50.0);
    });

    it('returns 0 when only non-priority PUS are reached', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 3, biaya: 20000, durasi: 15, kbModern: false);
        createPusWithScreeningAndIntervention($this, totalSkor: 5, biaya: 20000, durasi: 15, kbModern: false);

        expect($this->service->ketepatanSasaran())->toBe(0.0);
    });
});

// ──── Biaya per PUS Prioritas ─────────────────────────────

describe('biayaPerPusPrioritas', function () {
    it('returns 0 when no priority PUS are reached', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 3, biaya: 50000, durasi: 30, kbModern: false);

        expect($this->service->biayaPerPusPrioritas())->toBe(0.0);
    });

    it('calculates cost per priority PUS correctly', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 100000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 8, biaya: 50000, durasi: 20, kbModern: false);

        // Total cost: 150000, 2 priority PUS → 75000
        expect($this->service->biayaPerPusPrioritas())->toBe(75000.0);
    });

    it('only counts priority interventions in cost calculation', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 100000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 3, biaya: 50000, durasi: 20, kbModern: false);

        // Only the priority PUS counts: 100000 / 1 = 100000
        expect($this->service->biayaPerPusPrioritas())->toBe(100000.0);
    });
});

// ──── Efisiensi Waktu ─────────────────────────────────────

describe('efisiensiWaktu', function () {
    it('returns 0 when no interventions exist', function () {
        expect($this->service->efisiensiWaktu())->toBe(0.0);
    });

    it('calculates average duration correctly', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 8, biaya: 30000, durasi: 20, kbModern: false);

        // Average: (30 + 20) / 2 = 25
        expect($this->service->efisiensiWaktu())->toBe(25.0);
    });

    it('handles single intervention', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 45, kbModern: true);

        expect($this->service->efisiensiWaktu())->toBe(45.0);
    });
});

// ──── Konversi KB Modern ──────────────────────────────────

describe('konversiKbModern', function () {
    it('returns 0 when no interventions exist', function () {
        expect($this->service->konversiKbModern())->toBe(0.0);
    });

    it('returns 100 when all PUS use KB modern', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 8, biaya: 30000, durasi: 20, kbModern: true);

        expect($this->service->konversiKbModern())->toBe(100.0);
    });

    it('returns correct percentage with mixed KB adoption', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 8, biaya: 30000, durasi: 20, kbModern: false);

        // 1 out of 2 = 50%
        expect($this->service->konversiKbModern())->toBe(50.0);
    });

    it('returns 0 when no PUS use KB modern', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 50000, durasi: 30, kbModern: false);

        expect($this->service->konversiKbModern())->toBe(0.0);
    });
});

// ──── allMetrics ──────────────────────────────────────────

describe('allMetrics', function () {
    it('returns all 4 metrics at once', function () {
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 100000, durasi: 30, kbModern: true);
        createPusWithScreeningAndIntervention($this, totalSkor: 4, biaya: 20000, durasi: 20, kbModern: false);

        $metrics = $this->service->allMetrics();

        expect($metrics)->toHaveKeys([
            'ketepatan_sasaran',
            'biaya_per_pus_prioritas',
            'efisiensi_waktu',
            'konversi_kb_modern',
        ])
            ->and($metrics['ketepatan_sasaran'])->toBe(50.0)
            ->and($metrics['biaya_per_pus_prioritas'])->toBe(100000.0)
            ->and($metrics['efisiensi_waktu'])->toBe(25.0)
            ->and($metrics['konversi_kb_modern'])->toBe(50.0);
    });

    it('returns all zeros when no data exists', function () {
        $metrics = $this->service->allMetrics();

        expect($metrics['ketepatan_sasaran'])->toBe(0.0)
            ->and($metrics['biaya_per_pus_prioritas'])->toBe(0.0)
            ->and($metrics['efisiensi_waktu'])->toBe(0.0)
            ->and($metrics['konversi_kb_modern'])->toBe(0.0);
    });
});

// ──── Filtering ───────────────────────────────────────────

describe('territory filtering', function () {
    it('filters by kecamatan_id', function () {
        $otherKec = Kecamatan::create(['name' => 'Belinyu', 'code' => '19.01.05']);
        $otherDesa = Desa::create(['kecamatan_id' => $otherKec->id, 'name' => 'Belinyu', 'code' => '19.01.05.2001']);

        // Create PUS in Sungailiat
        createPusWithScreeningAndIntervention($this, totalSkor: 12, biaya: 100000, durasi: 30, kbModern: true);

        // Create PUS in Belinyu (different kecamatan)
        $otherPus = PusProfile::create([
            'user_id' => $this->user->id,
            'kecamatan_id' => $otherKec->id,
            'desa_id' => $otherDesa->id,
            'nama_istri' => 'Siti',
            'nama_suami' => 'Ahmad',
            'tanggal_lahir_istri' => now()->subYears(28),
            'jumlah_anak_lahir_hidup' => 1,
        ]);
        $otherScreening = Screening::create([
            'pus_profile_id' => $otherPus->id,
            'user_id' => $this->user->id,
            'p1_usia_istri' => 3, 'p2_jumlah_anak' => 3,
            'p3_status_kontrasepsi' => 1, 'p4_tujuan_reproduksi' => 1, 'p5_risiko_kehamilan' => 1,
            'total_skor' => 9,
            'kategori' => 'sedang', 'is_mupar' => true,
            'prioritas_akhir' => 'Prioritas Edukasi Terjadwal', 'urutan_antrean' => 3,
            'tanggal_skrining' => now(),
        ]);
        Intervention::create([
            'screening_id' => $otherScreening->id,
            'user_id' => $this->user->id,
            'tanggal_kunjungan' => now()->toDateString(),
            'jenis_tindakan' => 'Konseling',
            'durasi_menit' => 45,
            'biaya_operasional' => 50000,
            'menggunakan_kb_modern' => false,
        ]);

        // Filter for Sungailiat only → should only see 1 PUS
        $metrics = $this->service->allMetrics(['kecamatan_id' => $this->kecamatan->id]);
        expect($metrics['ketepatan_sasaran'])->toBe(100.0);

        // No filter → should see 2 PUS
        $metricsAll = $this->service->allMetrics();
        expect($metricsAll['ketepatan_sasaran'])->toBe(100.0); // both are priority (12 and 9 are both >= 7)
    });
});

describe('date filtering', function () {
    it('filters by date range', function () {
        createPusWithScreeningAndIntervention(
            $this, totalSkor: 12, biaya: 100000, durasi: 30, kbModern: true,
            tanggalKunjungan: '2026-01-15',
        );
        createPusWithScreeningAndIntervention(
            $this, totalSkor: 8, biaya: 50000, durasi: 20, kbModern: false,
            tanggalKunjungan: '2026-06-15',
        );

        // Filter for Jan only
        $janMetrics = $this->service->allMetrics([
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
        ]);
        expect($janMetrics['konversi_kb_modern'])->toBe(100.0); // only 1 PUS, uses KB modern

        // Filter for Jun only
        $junMetrics = $this->service->allMetrics([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]);
        expect($junMetrics['konversi_kb_modern'])->toBe(0.0); // only 1 PUS, no KB modern
    });
});
