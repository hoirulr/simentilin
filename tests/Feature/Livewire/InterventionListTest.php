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

    $this->kec1 = Kecamatan::find(1); // Sungailiat
    $this->desa1 = Desa::where('kecamatan_id', 1)->first();

    $this->kec2 = Kecamatan::find(2); // Merawang
    $this->desa2 = Desa::where('kecamatan_id', 2)->first();

    $this->petugas1 = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kec1->id,
        'desa_id' => $this->desa1->id,
    ]);

    $this->petugas2 = User::factory()->create([
        'name' => 'PKB Merawang',
        'email' => 'pkb.merawang@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kec2->id,
        'desa_id' => $this->desa2->id,
    ]);

    // PUS 1 in Sungailiat
    $this->pus1 = PusProfile::create([
        'user_id' => $this->petugas1->id,
        'kecamatan_id' => $this->kec1->id,
        'desa_id' => $this->desa1->id,
        'nama_istri' => 'Nurul Hidayati',
        'nama_suami' => 'Hendra Pratama',
        'tanggal_lahir_istri' => '2000-01-01',
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    $this->screening1 = Screening::create([
        'pus_profile_id' => $this->pus1->id,
        'user_id' => $this->petugas1->id,
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

    $this->intervention1 = Intervention::create([
        'screening_id' => $this->screening1->id,
        'user_id' => $this->petugas1->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'deskripsi_tindakan' => 'Kunjungan rumah PUS MUPAR prioritas utama.',
        'durasi_menit' => 45,
        'biaya_operasional' => 35000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
        'catatan' => 'PUS setuju menggunakan implan 3 tahun.',
    ]);

    // PUS 2 in Merawang
    $this->pus2 = PusProfile::create([
        'user_id' => $this->petugas2->id,
        'kecamatan_id' => $this->kec2->id,
        'desa_id' => $this->desa2->id,
        'nama_istri' => 'Siti Aminah',
        'nama_suami' => 'Ahmad Fauzi',
        'tanggal_lahir_istri' => '1988-06-12',
        'jumlah_anak_lahir_hidup' => 3,
    ]);

    $this->screening2 = Screening::create([
        'pus_profile_id' => $this->pus2->id,
        'user_id' => $this->petugas2->id,
        'p1_usia_istri' => 2,
        'p2_jumlah_anak' => 2,
        'p3_status_kontrasepsi' => 2,
        'p4_tujuan_reproduksi' => 2,
        'p5_risiko_kehamilan' => 2,
        'total_skor' => 10,
        'kategori' => 'sedang',
        'is_mupar' => false,
        'prioritas_akhir' => 'Prioritas Sedang',
        'urutan_antrean' => 4,
        'tanggal_skrining' => now()->subDay()->format('Y-m-d'),
    ]);

    $this->intervention2 = Intervention::create([
        'screening_id' => $this->screening2->id,
        'user_id' => $this->petugas2->id,
        'tanggal_kunjungan' => now()->subDay()->format('Y-m-d'),
        'jenis_tindakan' => 'Edukasi Terjadwal',
        'deskripsi_tindakan' => 'Konseling metode kontrasepsi pil.',
        'durasi_menit' => 30,
        'biaya_operasional' => 20000,
        'menggunakan_kb_modern' => false,
        'metode_kb' => 'Belum Ber-KB',
        'catatan' => 'Masih berdiskusi dengan suami.',
    ]);
});

test('petugas lapangan only sees interventions in their assigned territory', function () {
    $this->actingAs($this->petugas1);

    Livewire::test('pages::interventions.index')
        ->assertSee('Nurul Hidayati')
        ->assertSee('Implan / Susuk')
        ->assertDontSee('Siti Aminah');
});

test('admin dinas sees interventions across all territories', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::interventions.index')
        ->assertSee('Nurul Hidayati')
        ->assertSee('Siti Aminah')
        ->assertSee('Total Biaya Riil');
});

test('unauthenticated users are redirected to login', function () {
    $response = $this->get(route('interventions.index'));
    $response->assertRedirect(route('login'));
});

test('can filter by modern kb status and search', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::interventions.index')
        ->set('filter_kb_modern', 'yes')
        ->assertSee('Nurul Hidayati')
        ->assertDontSee('Siti Aminah')
        ->set('filter_kb_modern', 'no')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Nurul Hidayati')
        ->set('filter_kb_modern', '')
        ->set('search', 'Nurul')
        ->assertSee('Nurul Hidayati')
        ->assertDontSee('Siti Aminah');
});

test('can open and view detailed intervention proof sheet modal', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::interventions.index')
        ->assertSet('showDetailModal', false)
        ->call('openDetailModal', $this->intervention1->id)
        ->assertSet('showDetailModal', true)
        ->assertSee('Lembar Bukti Realisasi Intervensi')
        ->assertSee('Ny. Nurul Hidayati')
        ->assertSee('KIE / Konseling KB Segera')
        ->call('closeDetailModal')
        ->assertSet('showDetailModal', false);
});
