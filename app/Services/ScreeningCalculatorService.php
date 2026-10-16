<?php

namespace App\Services;

use App\Enums\PriorityCategory;

/**
 * Pure business logic service for SI-MENTILIN screening calculations.
 *
 * Implements the 5-indicator scoring system (P1-P5, each 0-3, total 0-15)
 * and MUPAR strategic flag matrix as defined in PRD §3.1 and §3.2.
 *
 * CRITICAL RULE: MUPAR is NOT a scoring parameter. It is a boolean flag
 * combined with the score category to determine final priority.
 * MUPAR value must NEVER be added to total_skor.
 */
class ScreeningCalculatorService
{
    /**
     * Calculate P1: Wife's age score.
     *
     * | Score | Condition      |
     * |-------|----------------|
     * | 0     | ≥ 45 tahun     |
     * | 1     | 40–44 tahun    |
     * | 2     | 36–39 tahun    |
     * | 3     | ≤ 35 tahun     |
     */
    public function calculateP1(int $usiaIstri): int
    {
        return match (true) {
            $usiaIstri >= 45 => 0,
            $usiaIstri >= 40 => 1,
            $usiaIstri >= 36 => 2,
            default => 3,
        };
    }

    /**
     * Calculate P2: Number of living children score.
     *
     * | Score | Condition |
     * |-------|-----------|
     * | 0     | ≥ 5 anak  |
     * | 1     | 4 anak    |
     * | 2     | 3 anak    |
     * | 3     | 0–2 anak  |
     */
    public function calculateP2(int $jumlahAnak): int
    {
        return match (true) {
            $jumlahAnak >= 5 => 0,
            $jumlahAnak === 4 => 1,
            $jumlahAnak === 3 => 2,
            default => 3,
        };
    }

    /**
     * Calculate the total score from the 5 indicator parameters.
     *
     * Each parameter must be in range 0-3. Total range: 0-15.
     * Formula: total_skor = P1 + P2 + P3 + P4 + P5
     *
     * @param  int  $p1  Usia istri score (0-3)
     * @param  int  $p2  Jumlah anak lahir hidup score (0-3)
     * @param  int  $p3  Status kontrasepsi score (0-3)
     * @param  int  $p4  Tujuan reproduksi score (0-3)
     * @param  int  $p5  Risiko kehamilan score (0-3)
     *
     * @throws \InvalidArgumentException if any parameter is outside 0-3 range
     */
    public function calculateTotalScore(int $p1, int $p2, int $p3, int $p4, int $p5): int
    {
        $params = compact('p1', 'p2', 'p3', 'p4', 'p5');

        foreach ($params as $name => $value) {
            if ($value < 0 || $value > 3) {
                throw new \InvalidArgumentException(
                    "Parameter {$name} must be between 0 and 3, got {$value}."
                );
            }
        }

        return $p1 + $p2 + $p3 + $p4 + $p5;
    }

    /**
     * Determine the priority category from total score.
     */
    public function determineCategory(int $totalScore): PriorityCategory
    {
        return PriorityCategory::fromScore($totalScore);
    }

    /**
     * Determine MUPAR (Muda Paritas Rendah) status.
     *
     * A PUS is considered MUPAR when the wife is young (≤ 35 years)
     * with low parity (0-2 living children).
     */
    public function determineMupar(int $usiaIstri, int $jumlahAnak): bool
    {
        return $usiaIstri <= 35 && $jumlahAnak <= 2;
    }

    /**
     * Calculate the final priority determination.
     *
     * Combines score category with MUPAR flag to produce the final
     * priority label and queue order (1-5) per PRD §3.2 matrix.
     *
     * @return array{
     *     p1_usia_istri: int,
     *     p2_jumlah_anak: int,
     *     p3_status_kontrasepsi: int,
     *     p4_tujuan_reproduksi: int,
     *     p5_risiko_kehamilan: int,
     *     total_skor: int,
     *     kategori: PriorityCategory,
     *     is_mupar: bool,
     *     prioritas_akhir: string,
     *     urutan_antrean: int
     * }
     *
     * @throws \InvalidArgumentException if any parameter is outside 0-3 range
     */
    public function calculate(
        int $usiaIstri,
        int $jumlahAnak,
        int $p3,
        int $p4,
        int $p5,
    ): array {
        $p1 = $this->calculateP1($usiaIstri);
        $p2 = $this->calculateP2($jumlahAnak);

        $totalScore = $this->calculateTotalScore($p1, $p2, $p3, $p4, $p5);
        $category = $this->determineCategory($totalScore);
        $isMupar = $this->determineMupar($usiaIstri, $jumlahAnak);

        $finalPriority = $category->finalPriority($isMupar);

        return [
            'p1_usia_istri' => $p1,
            'p2_jumlah_anak' => $p2,
            'p3_status_kontrasepsi' => $p3,
            'p4_tujuan_reproduksi' => $p4,
            'p5_risiko_kehamilan' => $p5,
            'total_skor' => $totalScore,
            'kategori' => $category,
            'is_mupar' => $isMupar,
            'prioritas_akhir' => $finalPriority['label'],
            'urutan_antrean' => $finalPriority['queue_order'],
        ];
    }
}
