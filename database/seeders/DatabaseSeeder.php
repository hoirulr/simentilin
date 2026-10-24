<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use App\Services\ScreeningCalculatorService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(WilayahSeeder::class);

        // Create default admin dinas account
        User::firstOrCreate(
            ['email' => 'admin@simentilin.bangka.go.id'],
            [
                'name' => 'Admin DP2KBP3A',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => UserRole::ADMIN_DINAS,
                'email_verified_at' => now(),
                'remember_token' => \Illuminate\Support\Str::random(10),
            ]
        );

        // Create default petugas lapangan account
        $pkb = User::firstOrCreate(
            ['email' => 'pkb.sungailiat@simentilin.bangka.go.id'],
            [
                'name' => 'PKB Sungailiat',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'role' => UserRole::PETUGAS_LAPANGAN,
                'kecamatan_id' => 1,
                'desa_id' => 1,
                'email_verified_at' => now(),
                'remember_token' => \Illuminate\Support\Str::random(10),
            ]
        );

        $calculator = new ScreeningCalculatorService;

        // 1. PUS MUPAR Skor Tinggi -> Prioritas Utama (#1)
        $pus1 = PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'nama_istri' => 'Nurul Hidayati',
            'nama_suami' => 'Hendra Pratama',
            'tanggal_lahir_istri' => now()->subYears(24)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 1,
            'rt_rw' => 'RT 02 / RW 01',
            'alamat' => 'Kampung Nelayan, Sungailiat',
        ]);
        $c1 = $calculator->calculate($pus1->usiaIstri(), $pus1->jumlah_anak_lahir_hidup, 3, 3, 3);
        Screening::create([
            'pus_profile_id' => $pus1->id,
            'user_id' => $pkb->id,
            'p1_usia_istri' => $c1['p1_usia_istri'],
            'p2_jumlah_anak' => $c1['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => 3,
            'p4_tujuan_reproduksi' => 3,
            'p5_risiko_kehamilan' => 3,
            'total_skor' => $c1['total_skor'],
            'kategori' => $c1['kategori']->value,
            'is_mupar' => $c1['is_mupar'],
            'prioritas_akhir' => $c1['prioritas_akhir'],
            'urutan_antrean' => $c1['urutan_antrean'],
            'tanggal_skrining' => now()->format('Y-m-d'),
            'catatan' => 'PUS muda, anak 1 usia 8 bulan, belum ber-KB, takut efek samping suntik.',
        ]);

        // 2. PUS Non-MUPAR Skor Tinggi -> Prioritas Tinggi (#2)
        $pus2 = PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'nama_istri' => 'Rosita Dewi',
            'nama_suami' => 'Bambang Supriyadi',
            'tanggal_lahir_istri' => now()->subYears(41)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 4,
            'rt_rw' => 'RT 03 / RW 02',
            'alamat' => 'Jl. Jenderal Sudirman No. 45',
        ]);
        $c2 = $calculator->calculate($pus2->usiaIstri(), $pus2->jumlah_anak_lahir_hidup, 3, 3, 3);
        Screening::create([
            'pus_profile_id' => $pus2->id,
            'user_id' => $pkb->id,
            'p1_usia_istri' => $c2['p1_usia_istri'],
            'p2_jumlah_anak' => $c2['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => 3,
            'p4_tujuan_reproduksi' => 3,
            'p5_risiko_kehamilan' => 3,
            'total_skor' => $c2['total_skor'],
            'kategori' => $c2['kategori']->value,
            'is_mupar' => $c2['is_mupar'],
            'prioritas_akhir' => $c2['prioritas_akhir'],
            'urutan_antrean' => $c2['urutan_antrean'],
            'tanggal_skrining' => now()->subDays(1)->format('Y-m-d'),
            'catatan' => 'Usia 41 tahun, anak 4, ingin stop anak tapi suami belum setuju MOW/kondom.',
        ]);

        // 3. PUS MUPAR Skor Sedang -> Prioritas Edukasi Terjadwal (#3)
        $pus3 = PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 2,
            'nama_istri' => 'Anisa Lestari',
            'nama_suami' => 'Rian Hidayat',
            'tanggal_lahir_istri' => now()->subYears(28)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 1,
            'rt_rw' => 'RT 01 / RW 01',
            'alamat' => 'Kelurahan Kudai',
        ]);
        $c3 = $calculator->calculate($pus3->usiaIstri(), $pus3->jumlah_anak_lahir_hidup, 1, 1, 1);
        Screening::create([
            'pus_profile_id' => $pus3->id,
            'user_id' => $pkb->id,
            'p1_usia_istri' => $c3['p1_usia_istri'],
            'p2_jumlah_anak' => $c3['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => 1,
            'p4_tujuan_reproduksi' => 1,
            'p5_risiko_kehamilan' => 1,
            'total_skor' => $c3['total_skor'],
            'kategori' => $c3['kategori']->value,
            'is_mupar' => $c3['is_mupar'],
            'prioritas_akhir' => $c3['prioritas_akhir'],
            'urutan_antrean' => $c3['urutan_antrean'],
            'tanggal_skrining' => now()->subDays(2)->format('Y-m-d'),
            'catatan' => 'Sedang mempertimbangkan implan, butuh penjelasan lanjutan.',
        ]);

        // 4. PUS Non-MUPAR Skor Sedang -> Prioritas Sedang (#4)
        $pus4 = PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 3,
            'nama_istri' => 'Marlina Susanti',
            'nama_suami' => 'Dedi Gunawan',
            'tanggal_lahir_istri' => now()->subYears(38)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 3,
            'rt_rw' => 'RT 04 / RW 02',
            'alamat' => 'Kenanga',
        ]);
        $c4 = $calculator->calculate($pus4->usiaIstri(), $pus4->jumlah_anak_lahir_hidup, 2, 1, 1);
        Screening::create([
            'pus_profile_id' => $pus4->id,
            'user_id' => $pkb->id,
            'p1_usia_istri' => $c4['p1_usia_istri'],
            'p2_jumlah_anak' => $c4['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => 2,
            'p4_tujuan_reproduksi' => 1,
            'p5_risiko_kehamilan' => 1,
            'total_skor' => $c4['total_skor'],
            'kategori' => $c4['kategori']->value,
            'is_mupar' => $c4['is_mupar'],
            'prioritas_akhir' => $c4['prioritas_akhir'],
            'urutan_antrean' => $c4['urutan_antrean'],
            'tanggal_skrining' => now()->subDays(3)->format('Y-m-d'),
            'catatan' => 'Penggunaan pil sering terlewat, disarankan beralih ke suntik 3 bulanan.',
        ]);

        // 5. PUS Belum Diskrining (Siap untuk dites pengguna)
        PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'nama_istri' => 'Fitri Anggraini',
            'nama_suami' => 'Doni Saputra',
            'tanggal_lahir_istri' => now()->subYears(22)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 0,
            'rt_rw' => 'RT 01 / RW 01',
            'alamat' => 'Samping Kantor Camat Sungailiat',
        ]);

        PusProfile::create([
            'user_id' => $pkb->id,
            'kecamatan_id' => 1,
            'desa_id' => 4,
            'nama_istri' => 'Ratna Juwita',
            'nama_suami' => 'Agus Triyono',
            'tanggal_lahir_istri' => now()->subYears(34)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 2,
            'rt_rw' => 'RT 02 / RW 03',
            'alamat' => 'Sinar Baru',
        ]);
    }
}
