<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->kecamatanSungailiat = Kecamatan::where('name', 'Sungailiat')->first() ?? Kecamatan::first();
    $this->desaSungailiat = Desa::where('kecamatan_id', $this->kecamatanSungailiat->id)->first();

    $this->kecamatanBelinyu = Kecamatan::where('name', 'Belinyu')->first() ?? Kecamatan::skip(1)->first();
    $this->desaBelinyu = Desa::where('kecamatan_id', $this->kecamatanBelinyu->id)->first();

    // Admin Dinas
    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
    ]);

    // Petugas Lapangan Sungailiat
    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatanSungailiat->id,
        'desa_id' => $this->desaSungailiat->id,
    ]);

    // Create PUS 1 in Sungailiat (with intervention completed)
    $pus1 = PusProfile::create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatanSungailiat->id,
        'desa_id' => $this->desaSungailiat->id,
        'nama_istri' => 'Siti Nurhaliza',
        'nama_suami' => 'Budi Santoso',
        'tanggal_lahir_istri' => '1998-05-15',
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    // Screening Prioritas Utama (Skor 14 + MUPAR)
    $screening1 = Screening::create([
        'pus_profile_id' => $pus1->id,
        'user_id' => $this->petugas->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 3,
        'p4_tujuan_reproduksi' => 2,
        'p5_risiko_kehamilan' => 3,
        'total_skor' => 14,
        'kategori' => 'tinggi',
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);

    // Realisasi Intervensi for PUS 1
    Intervention::create([
        'screening_id' => $screening1->id,
        'user_id' => $this->petugas->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'durasi_menit' => 45,
        'biaya_operasional' => 50000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'IUD / Spiral',
    ]);

    // Create PUS 2 in Sungailiat (urgent unaddressed priority)
    $pus2 = PusProfile::create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatanSungailiat->id,
        'desa_id' => $this->desaSungailiat->id,
        'nama_istri' => 'Dewi Lestari',
        'nama_suami' => 'Agus Setiawan',
        'tanggal_lahir_istri' => '2001-08-20',
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    $screening2 = Screening::create([
        'pus_profile_id' => $pus2->id,
        'user_id' => $this->petugas->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 3,
        'p4_tujuan_reproduksi' => 3,
        'p5_risiko_kehamilan' => 3,
        'total_skor' => 15,
        'kategori' => 'tinggi',
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);
});

test('petugas lapangan can access rekap wilayah page', function () {
    $this->actingAs($this->petugas);

    $response = $this->get(route('laporan.index'));

    $response->assertOk();
    $response->assertSee('Rekapitulasi Capaian Wilayah Binaan');
    $response->assertSee($this->desaSungailiat->name);
});

test('unauthenticated user cannot access rekap wilayah page', function () {
    $response = $this->get(route('laporan.index'));
    $response->assertRedirect(route('login'));
});

test('rekap wilayah livewire component loads data and filters properly', function () {
    $this->actingAs($this->petugas);

    Livewire::test('pages::laporan.index')
        ->assertSee($this->desaSungailiat->name)
        ->assertSee('Dewi Lestari')
        ->set('periodeBulan', (string) now()->month)
        ->assertSee($this->desaSungailiat->name)
        ->assertSee('Dewi Lestari');
});

test('petugas lapangan can trigger CSV export on rekap wilayah', function () {
    $this->actingAs($this->petugas);

    Livewire::test('pages::laporan.index')
        ->call('exportCsv')
        ->assertFileDownloaded();
});

test('admin dinas can access ekspor laporan kabupaten page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.laporan'));

    $response->assertOk();
    $response->assertSee('Ekspor Laporan Resmi Kabupaten Bangka');
    $response->assertSee('Matriks Rekapitulasi Capaian Antar Kecamatan');
    $response->assertSee('Sungailiat');
});

test('petugas lapangan is forbidden from accessing admin laporan page', function () {
    $this->actingAs($this->petugas);

    $response = $this->get(route('admin.laporan'));

    $response->assertForbidden();
});

test('admin laporan livewire component filters and exports CSV properly', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.laporan')
        ->assertSee('Sungailiat')
        ->assertSee('Belinyu')
        ->assertSee('IUD / Spiral')
        ->set('selectedKecamatanId', (string) $this->kecamatanSungailiat->id)
        ->assertSee('Sungailiat')
        ->call('exportCsv')
        ->assertFileDownloaded();
});
