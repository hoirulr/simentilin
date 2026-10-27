<?php

use App\Enums\PriorityCategory;
use App\Services\ScreeningCalculatorService;

beforeEach(function () {
    $this->service = new ScreeningCalculatorService;
});

describe('P2-03: Boundary Score & MUPAR Matrix Invariants', function () {

    /**
     * Data provider matrix for PRD §3.2:
     * [score, isMupar, expectedCategory, expectedLabel, expectedQueue]
     */
    $matrix = [
        // Boundary 0 (Minimum possible score)
        'Score 0 + MUPAR' => [0, true, PriorityCategory::RENDAH, 'Monitoring / Edukasi Rutin', 5],
        'Score 0 + Non-MUPAR' => [0, false, PriorityCategory::RENDAH, 'Monitoring / Edukasi Rutin', 5],

        // Boundary 6 (Maximum score for Rendah)
        'Score 6 + MUPAR' => [6, true, PriorityCategory::RENDAH, 'Monitoring / Edukasi Rutin', 5],
        'Score 6 + Non-MUPAR' => [6, false, PriorityCategory::RENDAH, 'Monitoring / Edukasi Rutin', 5],

        // Boundary 7 (Minimum score for Sedang)
        'Score 7 + MUPAR' => [7, true, PriorityCategory::SEDANG, 'Prioritas Edukasi Terjadwal', 3],
        'Score 7 + Non-MUPAR' => [7, false, PriorityCategory::SEDANG, 'Prioritas Sedang', 4],

        // Boundary 10 (Maximum score for Sedang)
        'Score 10 + MUPAR' => [10, true, PriorityCategory::SEDANG, 'Prioritas Edukasi Terjadwal', 3],
        'Score 10 + Non-MUPAR' => [10, false, PriorityCategory::SEDANG, 'Prioritas Sedang', 4],

        // Boundary 11 (Minimum score for Tinggi)
        'Score 11 + MUPAR' => [11, true, PriorityCategory::TINGGI, 'Prioritas Utama', 1],
        'Score 11 + Non-MUPAR' => [11, false, PriorityCategory::TINGGI, 'Prioritas Tinggi', 2],

        // Boundary 15 (Maximum possible score)
        'Score 15 + MUPAR' => [15, true, PriorityCategory::TINGGI, 'Prioritas Utama', 1],
        'Score 15 + Non-MUPAR' => [15, false, PriorityCategory::TINGGI, 'Prioritas Tinggi', 2],
    ];

    foreach ($matrix as $description => [$score, $isMupar, $expectedCategory, $expectedLabel, $expectedQueue]) {
        it("verifies {$description}", function () use ($score, $isMupar, $expectedCategory, $expectedLabel, $expectedQueue) {
            $category = $this->service->determineCategory($score);
            expect($category)->toBe($expectedCategory);

            $final = $category->finalPriority($isMupar);
            expect($final['label'])->toBe($expectedLabel)
                ->and($final['queue_order'])->toBe($expectedQueue);
        });
    }
});

describe('P2-03: Mathematical Invariant Over All 1024 Permutations', function () {
    it('proves that total_skor is strictly the sum of 5 parameters and immune to MUPAR', function () {
        $count = 0;

        for ($p1 = 0; $p1 <= 3; $p1++) {
            for ($p2 = 0; $p2 <= 3; $p2++) {
                for ($p3 = 0; $p3 <= 3; $p3++) {
                    for ($p4 = 0; $p4 <= 3; $p4++) {
                        for ($p5 = 0; $p5 <= 3; $p5++) {
                            $count++;
                            $total = $this->service->calculateTotalScore($p1, $p2, $p3, $p4, $p5);

                            // Invariant 1: Range is always 0 to 15
                            expect($total)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(15);

                            // Invariant 2: Mathematical identity
                            expect($total)->toBe($p1 + $p2 + $p3 + $p4 + $p5);

                            // Invariant 3: Category mapping
                            $category = $this->service->determineCategory($total);
                            if ($total <= 6) {
                                expect($category)->toBe(PriorityCategory::RENDAH);
                            } elseif ($total <= 10) {
                                expect($category)->toBe(PriorityCategory::SEDANG);
                            } else {
                                expect($category)->toBe(PriorityCategory::TINGGI);
                            }

                            // Invariant 4: Queue order range [1, 5]
                            foreach ([true, false] as $mupar) {
                                $priority = $category->finalPriority($mupar);
                                expect($priority['queue_order'])->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(5);
                            }
                        }
                    }
                }
            }
        }

        expect($count)->toBe(1024);
    });
});
