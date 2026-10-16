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

    $this->kec1 = Kecamatan::find(1);
    $this->desa1 = Desa::where('kecamatan_id', 1)->first();

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kec1->id,
        'desa_id' => $this->desa1->id,
    ]);

    $pus = PusProfile::create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kec1->id,
        'desa_id' => $this->desa1->id,
        'nama_istri' => 'Nurul Hidayati',
        'nama_suami' => 'Hendra Pratama',
        'tanggal_lahir_istri' => '2000-01-01',
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    $screening = Screening::create([
        'pus_profile_id' => $pus->id,
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
        'screening_id' => $screening->id,
        'user_id' => $this->petugas->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'durasi_menit' => 30,
        'biaya_operasional' => 35000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
    ]);
});

test('authenticated user can access mcpr and tfr page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('mcpr.index'));

    $response->assertOk();
    $response->assertSee('Capaian MCPR & Tren Penurunan TFR');
    $response->assertSee('Capaian MCPR Riil');
    $response->assertSee('Estimasi TFR');
    $response->assertSee('Proporsi MKJP');
});

test('unauthenticated user is redirected to login for mcpr page', function () {
    $response = $this->get(route('mcpr.index'));
    $response->assertRedirect(route('login'));
});

test('renders 8 kecamatan breakdown matrix in mcpr page', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::mcpr.index')
        ->assertSee('Sungailiat')
        ->assertSee('Belinyu')
        ->assertSee('Merawang')
        ->assertSee('Mendo Barat')
        ->assertSee('Pemali')
        ->assertSee('Bakam')
        ->assertSee('Riau Silip')
        ->assertSee('Puding Besar');
});

test('filter kecamatan updates the page view', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::mcpr.index')
        ->set('filter_kecamatan_id', $this->kec1->id)
        ->assertSee('Sungailiat');
});
