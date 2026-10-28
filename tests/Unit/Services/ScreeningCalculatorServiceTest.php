<?php

use App\Enums\PriorityCategory;
use App\Services\ScreeningCalculatorService;

beforeEach(function () {
    $this->service = new ScreeningCalculatorService;
});

// ──── P1: Usia Istri ──────────────────────────────────────

describe('calculateP1 (Usia Istri)', function () {
    it('returns 0 for age >= 45', function () {
        expect($this->service->calculateP1(45))->toBe(0)
            ->and($this->service->calculateP1(50))->toBe(0)
            ->and($this->service->calculateP1(60))->toBe(0);
    });

    it('returns 1 for age 40-44', function () {
        expect($this->service->calculateP1(40))->toBe(1)
            ->and($this->service->calculateP1(42))->toBe(1)
            ->and($this->service->calculateP1(44))->toBe(1);
    });

    it('returns 2 for age 26-39', function () {
        expect($this->service->calculateP1(26))->toBe(2)
            ->and($this->service->calculateP1(30))->toBe(2)
            ->and($this->service->calculateP1(36))->toBe(2)
            ->and($this->service->calculateP1(39))->toBe(2);
    });

    it('returns 3 for age <= 25', function () {
        expect($this->service->calculateP1(25))->toBe(3)
            ->and($this->service->calculateP1(20))->toBe(3)
            ->and($this->service->calculateP1(18))->toBe(3);
    });
});

// ──── P2: Jumlah Anak Lahir Hidup ─────────────────────────

describe('calculateP2 (Jumlah Anak)', function () {
    it('returns 0 for >= 5 children', function () {
        expect($this->service->calculateP2(5))->toBe(0)
            ->and($this->service->calculateP2(7))->toBe(0)
            ->and($this->service->calculateP2(10))->toBe(0);
    });

    it('returns 1 for exactly 4 children', function () {
        expect($this->service->calculateP2(4))->toBe(1);
    });

    it('returns 2 for exactly 3 children', function () {
        expect($this->service->calculateP2(3))->toBe(2);
    });

    it('returns 3 for 0-2 children', function () {
        expect($this->service->calculateP2(0))->toBe(3)
            ->and($this->service->calculateP2(1))->toBe(3)
            ->and($this->service->calculateP2(2))->toBe(3);
    });
});

// ──── Total Score Calculation ──────────────────────────────

describe('calculateTotalScore', function () {
    it('sums all 5 parameters correctly', function () {
        expect($this->service->calculateTotalScore(0, 0, 0, 0, 0))->toBe(0)
            ->and($this->service->calculateTotalScore(3, 3, 3, 3, 3))->toBe(15)
            ->and($this->service->calculateTotalScore(1, 2, 3, 0, 1))->toBe(7)
            ->and($this->service->calculateTotalScore(2, 1, 2, 3, 2))->toBe(10);
    });

    it('throws exception for parameter below 0', function () {
        $this->service->calculateTotalScore(-1, 0, 0, 0, 0);
    })->throws(InvalidArgumentException::class, 'Parameter p1 must be between 0 and 3');

    it('throws exception for parameter above 3', function () {
        $this->service->calculateTotalScore(0, 0, 4, 0, 0);
    })->throws(InvalidArgumentException::class, 'Parameter p3 must be between 0 and 3');

    it('MUPAR value is never added to total score', function () {
        // Proving that total_skor = P1 + P2 + P3 + P4 + P5 only (max 15)
        // Even when MUPAR is true, total remains sum of 5 params
        $maxScore = $this->service->calculateTotalScore(3, 3, 3, 3, 3);
        expect($maxScore)->toBe(15);
    });
});

// ──── Category Determination ──────────────────────────────

describe('determineCategory', function () {
    it('returns RENDAH for scores 0-6', function () {
        expect($this->service->determineCategory(0))->toBe(PriorityCategory::RENDAH)
            ->and($this->service->determineCategory(3))->toBe(PriorityCategory::RENDAH)
            ->and($this->service->determineCategory(6))->toBe(PriorityCategory::RENDAH);
    });

    it('returns SEDANG for scores 7-10', function () {
        expect($this->service->determineCategory(7))->toBe(PriorityCategory::SEDANG)
            ->and($this->service->determineCategory(9))->toBe(PriorityCategory::SEDANG)
            ->and($this->service->determineCategory(10))->toBe(PriorityCategory::SEDANG);
    });

    it('returns TINGGI for scores 11-15', function () {
        expect($this->service->determineCategory(11))->toBe(PriorityCategory::TINGGI)
            ->and($this->service->determineCategory(13))->toBe(PriorityCategory::TINGGI)
            ->and($this->service->determineCategory(15))->toBe(PriorityCategory::TINGGI);
    });
});

// ──── MUPAR Determination ─────────────────────────────────

describe('determineMupar', function () {
    it('returns true when wife <= 35 AND children <= 2', function () {
        expect($this->service->determineMupar(25, 0))->toBeTrue()
            ->and($this->service->determineMupar(35, 2))->toBeTrue()
            ->and($this->service->determineMupar(30, 1))->toBeTrue();
    });

    it('returns false when wife > 35', function () {
        expect($this->service->determineMupar(36, 0))->toBeFalse()
            ->and($this->service->determineMupar(40, 1))->toBeFalse();
    });

    it('returns false when children > 2', function () {
        expect($this->service->determineMupar(25, 3))->toBeFalse()
            ->and($this->service->determineMupar(30, 5))->toBeFalse();
    });

    it('returns false when both conditions fail', function () {
        expect($this->service->determineMupar(40, 4))->toBeFalse();
    });
});

// ──── Full Calculation (Integration) ──────────────────────

describe('calculate (full pipeline)', function () {
    it('computes Prioritas Utama for high score + MUPAR', function () {
        // Young wife (25y, score P1=3), few children (1, P2=3), all high P3-P5
        $result = $this->service->calculate(
            usiaIstri: 25,
            jumlahAnak: 1,
            p3: 3,
            p4: 3,
            p5: 3,
        );

        expect($result['total_skor'])->toBe(15)
            ->and($result['kategori'])->toBe(PriorityCategory::TINGGI)
            ->and($result['is_mupar'])->toBeTrue()
            ->and($result['prioritas_akhir'])->toBe('Prioritas Utama')
            ->and($result['urutan_antrean'])->toBe(1);
    });

    it('computes Prioritas Tinggi for high score + Non-MUPAR', function () {
        // Older wife (40y, P1=1), many children (4, P2=1), high P3-P5
        $result = $this->service->calculate(
            usiaIstri: 40,
            jumlahAnak: 4,
            p3: 3,
            p4: 3,
            p5: 3,
        );

        expect($result['total_skor'])->toBe(11)
            ->and($result['kategori'])->toBe(PriorityCategory::TINGGI)
            ->and($result['is_mupar'])->toBeFalse()
            ->and($result['prioritas_akhir'])->toBe('Prioritas Tinggi')
            ->and($result['urutan_antrean'])->toBe(2);
    });

    it('computes Prioritas Edukasi Terjadwal for medium score + MUPAR', function () {
        // Young wife (24y, P1=3), few children (0, P2=3), low-medium P3-P5
        $result = $this->service->calculate(
            usiaIstri: 24,
            jumlahAnak: 0,
            p3: 0,
            p4: 0,
            p5: 1,
        );

        expect($result['total_skor'])->toBe(7)
            ->and($result['kategori'])->toBe(PriorityCategory::SEDANG)
            ->and($result['is_mupar'])->toBeTrue()
            ->and($result['prioritas_akhir'])->toBe('Prioritas Edukasi Terjadwal')
            ->and($result['urutan_antrean'])->toBe(3);
    });

    it('computes Prioritas Sedang for medium score + Non-MUPAR', function () {
        // Older wife (38y, P2=2), many children (3, P2=2), medium P3-P5
        $result = $this->service->calculate(
            usiaIstri: 38,
            jumlahAnak: 3,
            p3: 1,
            p4: 1,
            p5: 1,
        );

        expect($result['total_skor'])->toBe(7)
            ->and($result['kategori'])->toBe(PriorityCategory::SEDANG)
            ->and($result['is_mupar'])->toBeFalse()
            ->and($result['prioritas_akhir'])->toBe('Prioritas Sedang')
            ->and($result['urutan_antrean'])->toBe(4);
    });

    it('computes Monitoring / Edukasi Rutin for low score (with MUPAR)', function () {
        // Young wife (24y), few children (1), all low P3-P5
        $result = $this->service->calculate(
            usiaIstri: 24,
            jumlahAnak: 1,
            p3: 0,
            p4: 0,
            p5: 0,
        );

        expect($result['total_skor'])->toBe(6)
            ->and($result['kategori'])->toBe(PriorityCategory::RENDAH)
            ->and($result['is_mupar'])->toBeTrue()
            ->and($result['prioritas_akhir'])->toBe('Monitoring / Edukasi Rutin')
            ->and($result['urutan_antrean'])->toBe(5);
    });

    it('computes Monitoring / Edukasi Rutin for low score (without MUPAR)', function () {
        // Older wife (46y), many children (6), all zero P3-P5
        $result = $this->service->calculate(
            usiaIstri: 46,
            jumlahAnak: 6,
            p3: 0,
            p4: 0,
            p5: 0,
        );

        expect($result['total_skor'])->toBe(0)
            ->and($result['kategori'])->toBe(PriorityCategory::RENDAH)
            ->and($result['is_mupar'])->toBeFalse()
            ->and($result['prioritas_akhir'])->toBe('Monitoring / Edukasi Rutin')
            ->and($result['urutan_antrean'])->toBe(5);
    });

    it('includes individual P1-P5 scores in the result', function () {
        $result = $this->service->calculate(
            usiaIstri: 42,
            jumlahAnak: 3,
            p3: 2,
            p4: 1,
            p5: 3,
        );

        expect($result['p1_usia_istri'])->toBe(1)          // 40-44 → 1
            ->and($result['p2_jumlah_anak'])->toBe(2)      // 3 anak → 2
            ->and($result['p3_status_kontrasepsi'])->toBe(2)
            ->and($result['p4_tujuan_reproduksi'])->toBe(1)
            ->and($result['p5_risiko_kehamilan'])->toBe(3);
    });

    it('never includes MUPAR in total_skor calculation', function () {
        // Verify that for a MUPAR case, total_skor is strictly P1+P2+P3+P4+P5
        $result = $this->service->calculate(
            usiaIstri: 25,  // MUPAR: young
            jumlahAnak: 0,  // MUPAR: low parity
            p3: 2,
            p4: 2,
            p5: 2,
        );

        // P1=3 (age 25), P2=3 (0 children), P3=2, P4=2, P5=2 → total = 12
        expect($result['total_skor'])->toBe(12)
            ->and($result['is_mupar'])->toBeTrue();

        // Confirm total = P1+P2+P3+P4+P5, MUPAR adds NOTHING
        $manualSum = $result['p1_usia_istri']
            + $result['p2_jumlah_anak']
            + $result['p3_status_kontrasepsi']
            + $result['p4_tujuan_reproduksi']
            + $result['p5_risiko_kehamilan'];

        expect($result['total_skor'])->toBe($manualSum);
    });
});

// ──── Boundary Values ─────────────────────────────────────

describe('boundary values', function () {
    it('correctly handles score boundary 6→7 (Rendah→Sedang)', function () {
        // Score = 6 → Rendah
        $result6 = $this->service->calculate(25, 0, 0, 0, 0);
        expect($result6['total_skor'])->toBe(6)
            ->and($result6['kategori'])->toBe(PriorityCategory::RENDAH);

        // Score = 7 → Sedang
        $result7 = $this->service->calculate(25, 0, 0, 0, 1);
        expect($result7['total_skor'])->toBe(7)
            ->and($result7['kategori'])->toBe(PriorityCategory::SEDANG);
    });

    it('correctly handles score boundary 10→11 (Sedang→Tinggi)', function () {
        // Score = 10 → Sedang
        $result10 = $this->service->calculate(25, 0, 1, 1, 2);
        expect($result10['total_skor'])->toBe(10)
            ->and($result10['kategori'])->toBe(PriorityCategory::SEDANG);

        // Score = 11 → Tinggi
        $result11 = $this->service->calculate(25, 0, 2, 1, 2);
        expect($result11['total_skor'])->toBe(11)
            ->and($result11['kategori'])->toBe(PriorityCategory::TINGGI);
    });

    it('handles minimum score 0', function () {
        $result = $this->service->calculate(50, 10, 0, 0, 0);
        expect($result['total_skor'])->toBe(0)
            ->and($result['kategori'])->toBe(PriorityCategory::RENDAH);
    });

    it('handles maximum score 15', function () {
        $result = $this->service->calculate(20, 0, 3, 3, 3);
        expect($result['total_skor'])->toBe(15)
            ->and($result['kategori'])->toBe(PriorityCategory::TINGGI);
    });

    it('handles MUPAR boundary: age 35 with 2 children (edge case: true)', function () {
        expect($this->service->determineMupar(35, 2))->toBeTrue();
    });

    it('handles MUPAR boundary: age 36 with 2 children (edge case: false)', function () {
        expect($this->service->determineMupar(36, 2))->toBeFalse();
    });

    it('handles MUPAR boundary: age 35 with 3 children (edge case: false)', function () {
        expect($this->service->determineMupar(35, 3))->toBeFalse();
    });
});
