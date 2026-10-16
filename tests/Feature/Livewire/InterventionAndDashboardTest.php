<?php

use App\Enums\PriorityCategory;
use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->kecamatan = Kecamatan::first();
    $this->desa = Desa::where('kecamatan_id', $this->kecamatan->id)->first();

    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin.test@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
    ]);

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.test@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);

    $this->pus = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Siti Khadijah',
        'nama_suami' => 'Ahmad Dahlan',
        'tanggal_lahir_istri' => now()->subYears(26)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    $this->screening = Screening::create([
        'pus_profile_id' => $this->pus->id,
        'user_id' => $this->petugas->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 3,
        'p4_tujuan_reproduksi' => 3,
        'p5_risiko_kehamilan' => 3,
        'total_skor' => 15,
        'kategori' => PriorityCategory::TINGGI->value,
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
        'tanggal_skrining' => now()->format('Y-m-d'),
        'catatan' => 'PUS Prioritas Utama siap diintervensi.',
    ]);
});

test('intervention modal component renders and opens via event', function () {
    $this->actingAs($this->petugas);

    Livewire::test('intervention-modal')
        ->assertSet('isOpen', false)
        ->dispatch('open-intervention-modal', screeningId: $this->screening->id)
        ->assertSet('isOpen', true)
        ->assertSet('screeningId', $this->screening->id)
        ->assertSee('Siti Khadijah')
        ->assertSee('Prioritas Utama');
});

test('intervention modal validates input fields', function () {
    $this->actingAs($this->petugas);

    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screening->id)
        ->set('tanggal_kunjungan', '')
        ->set('durasi_menit', 0)
        ->set('biaya_operasional', -100)
        ->call('save')
        ->assertHasErrors(['tanggal_kunjungan', 'durasi_menit', 'biaya_operasional']);
});

test('intervention modal successfully records an intervention', function () {
    $this->actingAs($this->petugas);

    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screening->id)
        ->set('tanggal_kunjungan', now()->format('Y-m-d'))
        ->set('jenis_tindakan', 'KIE / Konseling KB')
        ->set('durasi_menit', 45)
        ->set('biaya_operasional', 30000)
        ->set('menggunakan_kb_modern', true)
        ->set('metode_kb', 'Implan / Susuk')
        ->set('deskripsi_tindakan', 'Konseling pemantapan MKJP bersama pasangan')
        ->set('catatan', 'Akseptor setuju pasang minggu depan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('intervention-saved')
        ->assertSet('isOpen', false);

    $this->assertDatabaseHas('interventions', [
        'screening_id' => $this->screening->id,
        'user_id' => $this->petugas->id,
        'jenis_tindakan' => 'KIE / Konseling KB',
        'durasi_menit' => 45,
        'biaya_operasional' => 30000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
    ]);
});

test('dashboard page renders for admin dinas with economic metrics and apexcharts', function () {
    $this->actingAs($this->admin);

    // Create an intervention to populate metrics
    Intervention::create([
        'screening_id' => $this->screening->id,
        'user_id' => $this->petugas->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB',
        'durasi_menit' => 35,
        'biaya_operasional' => 25000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'IUD / AKDR',
    ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Dashboard Eksekutif')
        ->assertSee('SI-MENTILIN')
        ->assertSee('Indikator Nilai Ekonomis Inovasi (PIM III)')
        ->assertSee('Visualisasi Capaian MCPR &amp; TFR Kabupaten Bangka', false)
        ->assertSee('Sebaran PUS Prioritas di 8 Kecamatan');

    Livewire::test('pages::dashboard.index')
        ->assertOk()
        ->assertSee('Dashboard Eksekutif')
        ->assertSee('Total PUS Binaan')
        ->assertSee('PUS Ter-skrining')
        ->assertSee('Prioritas Utama');
});

test('dashboard livewire component responds to territory filters', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::dashboard.index')
        ->assertOk()
        ->assertViewHas('totalPus', 1)
        ->assertViewHas('prioritasUtamaCount', 1)
        ->set('kecamatan_id', $this->kecamatan->id)
        ->assertSet('kecamatan_id', $this->kecamatan->id)
        ->call('resetFilters')
        ->assertSet('kecamatan_id', null);
});

test('dashboard locks kecamatan for petugas lapangan', function () {
    $this->actingAs($this->petugas);

    Livewire::test('pages::dashboard.index')
        ->assertSet('kecamatan_id', $this->kecamatan->id);
});
