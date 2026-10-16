<?php

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

    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
    ]);

    $this->kecamatan = Kecamatan::first();
    $this->desa = Desa::where('kecamatan_id', $this->kecamatan->id)->first();

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);

    // Priority PUS (Skor 15 >= 7) with intervention
    $pus1 = PusProfile::create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Nurul Hidayati',
        'nama_suami' => 'Hendra Pratama',
        'tanggal_lahir_istri' => '2000-01-01',
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    $screening1 = Screening::create([
        'pus_profile_id' => $pus1->id,
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

    Intervention::create([
        'screening_id' => $screening1->id,
        'user_id' => $this->petugas->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'durasi_menit' => 30,
        'biaya_operasional' => 35000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
    ]);
});

test('authenticated user can access analitik nilai ekonomis page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('analitik.index'));

    $response->assertOk();
    $response->assertSee('Analitik Nilai Ekonomis & Efisiensi Inovasi');
    $response->assertSee('Persentase Ketepatan Sasaran');
    $response->assertSee('Biaya per PUS Prioritas');
    $response->assertSee('Efisiensi Waktu Identifikasi');
    $response->assertSee('Konversi KB Modern (MCPR)');
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('analitik.index'));
    $response->assertRedirect(route('login'));
});

test('renders economic metrics and comparison matrix properly', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::analitik.index')
        ->assertSee('PILAR 1')
        ->assertSee('PILAR 2')
        ->assertSee('PILAR 3')
        ->assertSee('PILAR 4')
        ->assertSee('Matriks Komparasi Dampak Inovasi')
        ->assertSee('Perbandingan Performa Nilai Ekonomis Antar 8 Kecamatan')
        ->assertSee('Sungailiat');
});

test('territory filter updates the metrics', function () {
    $this->actingAs($this->admin);

    $otherKecamatan = Kecamatan::where('id', '!=', $this->kecamatan->id)->first();

    Livewire::test('pages::analitik.index')
        ->set('filter_kecamatan_id', $this->kecamatan->id)
        ->assertSee('100%') // 1 of 1 priority reached in this kec
        ->set('filter_kecamatan_id', $otherKecamatan->id)
        ->assertSee('0%'); // No interventions in other kec
});
