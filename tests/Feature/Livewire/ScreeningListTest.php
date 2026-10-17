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

    Intervention::create([
        'screening_id' => $this->screening1->id,
        'user_id' => $this->petugas1->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'durasi_menit' => 45,
        'biaya_operasional' => 35000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
    ]);

    // PUS 2 in Merawang
    $this->pus2 = PusProfile::create([
        'user_id' => $this->petugas2->id,
        'kecamatan_id' => $this->kec2->id,
        'desa_id' => $this->desa2->id,
        'nama_istri' => 'Siti Aminah',
        'nama_suami' => 'Ahmad Fauzi',
        'tanggal_lahir_istri' => '1985-06-12',
        'jumlah_anak_lahir_hidup' => 4,
    ]);

    $this->screening2 = Screening::create([
        'pus_profile_id' => $this->pus2->id,
        'user_id' => $this->petugas2->id,
        'p1_usia_istri' => 1,
        'p2_jumlah_anak' => 1,
        'p3_status_kontrasepsi' => 1,
        'p4_tujuan_reproduksi' => 1,
        'p5_risiko_kehamilan' => 1,
        'total_skor' => 5,
        'kategori' => 'rendah',
        'is_mupar' => false,
        'prioritas_akhir' => 'Monitoring / Edukasi Rutin',
        'urutan_antrean' => 5,
        'tanggal_skrining' => now()->subDay()->format('Y-m-d'),
    ]);
});

test('petugas lapangan only sees screenings in their assigned territory', function () {
    $this->actingAs($this->petugas1);

    Livewire::test('pages::screening.index')
        ->assertSee('Nurul Hidayati')
        ->assertSee('Skor 15/15')
        ->assertDontSee('Siti Aminah');
});

test('admin dinas sees screenings across all territories', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::screening.index')
        ->assertSee('Nurul Hidayati')
        ->assertSee('Siti Aminah')
        ->assertSee('Total Skrining');
});

test('unauthenticated users cannot access screenings database', function () {
    $response = $this->get(route('screening.index'));
    $response->assertRedirect(route('login'));
});

test('can filter by kategori and mupar status', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::screening.index')
        ->set('filter_kategori', 'tinggi')
        ->assertSee('Nurul Hidayati')
        ->assertDontSee('Siti Aminah')
        ->set('filter_kategori', 'rendah')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Nurul Hidayati')
        ->set('filter_kategori', '')
        ->set('filter_mupar', 'yes')
        ->assertSee('Nurul Hidayati')
        ->assertDontSee('Siti Aminah');
});

test('can filter by intervention status', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::screening.index')
        ->set('filter_status_intervensi', 'sudah')
        ->assertSee('Nurul Hidayati')
        ->assertDontSee('Siti Aminah')
        ->set('filter_status_intervensi', 'belum')
        ->assertSee('Siti Aminah')
        ->assertDontSee('Nurul Hidayati');
});

test('can open and view detailed 5 indicators screening audit modal', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::screening.index')
        ->assertSet('showDetailModal', false)
        ->call('openDetailModal', $this->screening1->id)
        ->assertSet('showDetailModal', true)
        ->assertSee('Lembar Audit Skrining SI-MENTILIN')
        ->assertSee('P1: Usia Istri')
        ->assertSee('P2: Jumlah Anak Lahir Hidup')
        ->assertSee('P3: Status Kontrasepsi')
        ->assertSee('P4: Tujuan Reproduksi')
        ->assertSee('P5: Risiko Kehamilan Tidak Direncanakan')
        ->call('closeDetailModal')
        ->assertSet('showDetailModal', false);
});
