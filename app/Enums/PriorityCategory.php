<?php

namespace App\Enums;

enum PriorityCategory: string
{
    case RENDAH = 'rendah';
    case SEDANG = 'sedang';
    case TINGGI = 'tinggi';

    /**
     * Determine the category from total score (0-15).
     */
    public static function fromScore(int $score): self
    {
        return match (true) {
            $score >= 11 => self::TINGGI,
            $score >= 7 => self::SEDANG,
            default => self::RENDAH,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::RENDAH => 'Rendah (0–6)',
            self::SEDANG => 'Sedang (7–10)',
            self::TINGGI => 'Tinggi (11–15)',
        };
    }

    /**
     * Get the final priority determination combining category with MUPAR flag.
     *
     * @return array{label: string, queue_order: int}
     */
    public function finalPriority(bool $isMupar): array
    {
        return match ($this) {
            self::TINGGI => $isMupar
                ? ['label' => 'Prioritas Utama', 'queue_order' => 1]
                : ['label' => 'Prioritas Tinggi', 'queue_order' => 2],
            self::SEDANG => $isMupar
                ? ['label' => 'Prioritas Edukasi Terjadwal', 'queue_order' => 3]
                : ['label' => 'Prioritas Sedang', 'queue_order' => 4],
            self::RENDAH => ['label' => 'Monitoring / Edukasi Rutin', 'queue_order' => 5],
        };
    }
}
