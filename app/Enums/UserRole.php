<?php

namespace App\Enums;

enum UserRole: string
{
    case PETUGAS_LAPANGAN = 'petugas_lapangan';
    case ADMIN_DINAS = 'admin_dinas';

    public function label(): string
    {
        return match ($this) {
            self::PETUGAS_LAPANGAN => 'Petugas Lapangan (PKB/PLKB)',
            self::ADMIN_DINAS => 'Admin Dinas (DP2KBP3A)',
        };
    }
}
