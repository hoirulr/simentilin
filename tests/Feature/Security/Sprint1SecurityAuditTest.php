<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $kecamatans = Kecamatan::take(2)->get();
    $this->kecamatanA = $kecamatans[0];
    $this->kecamatanB = $kecamatans[1];

    $this->desaA = Desa::where('kecamatan_id', $this->kecamatanA->id)->first();
    $this->desaB = Desa::where('kecamatan_id', $this->kecamatanB->id)->first();

    // Petugas Lapangan in Wilayah A
    $this->petugasA = User::factory()->create([
        'name' => 'Petugas Wilayah A',
        'email' => 'petugas.a@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatanA->id,
        'desa_id' => $this->desaA->id,
    ]);

    // Petugas Lapangan in Wilayah B
    $this->petugasB = User::factory()->create([
        'name' => 'Petugas Wilayah B',
        'email' => 'petugas.b@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatanB->id,
        'desa_id' => $this->desaB->id,
    ]);

    // Admin Dinas
    $this->admin = User::factory()->create([
        'name' => 'Admin Dinas DP2KBP3A',
        'email' => 'admin.dinas@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
        'kecamatan_id' => null,
        'desa_id' => null,
    ]);

    // PUS in Wilayah A
    $this->pusA = PusProfile::factory()->create([
        'user_id' => $this->petugasA->id,
        'kecamatan_id' => $this->kecamatanA->id,
        'desa_id' => $this->desaA->id,
        'nama_istri' => 'Ny. Aisyah Wilayah A',
        'nama_suami' => 'Tn. Ahmad',
        'tanggal_lahir_istri' => now()->subYears(26)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    // PUS in Wilayah B
    $this->pusB = PusProfile::factory()->create([
        'user_id' => $this->petugasB->id,
        'kecamatan_id' => $this->kecamatanB->id,
        'desa_id' => $this->desaB->id,
        'nama_istri' => 'Ny. Fatimah Wilayah B',
        'nama_suami' => 'Tn. Hasan',
        'tanggal_lahir_istri' => now()->subYears(30)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 2,
    ]);
});

/*
|--------------------------------------------------------------------------
| P0-01: Tutup bypass wilayah pada pembuatan screening
|--------------------------------------------------------------------------
*/

test('P0-01: petugas lapangan cannot create screening for PUS from another territory', function () {
    $this->actingAs($this->petugasA);

    // Petugas A tries to create screening targeting PUS B (which belongs to Kecamatan B / Desa B)
    Livewire::test('pages::screening.create')
        ->set('pus_id', $this->pusB->id)
        ->set('p3', 2)
        ->set('p4', 2)
        ->set('p5', 2)
        ->set('tanggal_skrining', now()->format('Y-m-d'))
        ->call('saveScreening')
        ->assertStatus(404);

    $this->assertDatabaseMissing('screenings', [
        'pus_profile_id' => $this->pusB->id,
        'user_id' => $this->petugasA->id,
    ]);
});

test('P0-01: screening score is strictly recalculated from server database record, client tampering ignored', function () {
    $this->actingAs($this->petugasA);

    // Petugas A selects their valid PUS A
    // In database: usia 26 -> P1 = 3 (usia <= 35), jumlah anak 1 -> P2 = 3 (anak <= 2), MUPAR = true
    // Attempt client state manipulation: try to override p1=0, p2=0, is_mupar=false
    Livewire::test('pages::screening.create')
        ->call('selectPus', $this->pusA->id)
        ->set('p1', 0)
        ->set('p2', 0)
        ->set('is_mupar', false)
        ->set('p3', 1) // 1
        ->set('p4', 1) // 1
        ->set('p5', 1) // 1
        ->set('tanggal_skrining', now()->format('Y-m-d'))
        ->call('saveScreening')
        ->assertHasNoErrors()
        ->assertRedirect('/screening/priority-queue');

    $screening = Screening::where('pus_profile_id', $this->pusA->id)->latest('id')->first();
    expect($screening)->not->toBeNull();
    // Server must have calculated P1=3 and P2=3, total = 3 + 3 + 1 + 1 + 1 = 9
    expect($screening->p1_usia_istri)->toBe(3);
    expect($screening->p2_jumlah_anak)->toBe(3);
    expect($screening->total_skor)->toBe(9);
    expect($screening->is_mupar)->toBeTrue();
    expect($screening->user_id)->toBe($this->petugasA->id);
});

/*
|--------------------------------------------------------------------------
| P0-02: Cegah pembuatan dan pemindahan PUS lintas wilayah
|--------------------------------------------------------------------------
*/

test('P0-02: petugas lapangan cannot create PUS in another territory even if sending altered form inputs', function () {
    $this->actingAs($this->petugasA);

    // Petugas A submits create PUS with new_kecamatan_id = B, new_desa_id = B
    Livewire::test('pages::pus.index')
        ->call('openCreateModal')
        ->set('nama_istri', 'Istri Coba Pindah')
        ->set('nama_suami', 'Suami Coba Pindah')
        ->set('tanggal_lahir_istri', now()->subYears(25)->format('Y-m-d'))
        ->set('jumlah_anak_lahir_hidup', 1)
        ->set('new_kecamatan_id', $this->kecamatanB->id)
        ->set('new_desa_id', $this->desaB->id)
        ->call('savePus')
        ->assertHasNoErrors();

    // Territory must be forced to petugasA assigned territory
    $pus = PusProfile::where('nama_istri', 'Istri Coba Pindah')->first();
    expect($pus)->not->toBeNull();
    expect($pus->kecamatan_id)->toBe($this->kecamatanA->id);
    expect($pus->desa_id)->toBe($this->desaA->id);
});

test('P0-02: petugas lapangan cannot move PUS territory during update', function () {
    $this->actingAs($this->petugasA);

    Livewire::test('pages::pus.index')
        ->call('openEditModal', $this->pusA->id)
        ->set('edit_nama_istri', 'Ny. Aisyah Nama Baru')
        ->set('edit_nama_suami', 'Tn. Ahmad')
        ->set('edit_tanggal_lahir_istri', now()->subYears(26)->format('Y-m-d'))
        ->set('edit_jumlah_anak_lahir_hidup', 1)
        ->set('edit_kecamatan_id', $this->kecamatanB->id)
        ->set('edit_desa_id', $this->desaB->id)
        ->call('updatePus')
        ->assertHasNoErrors();

    $this->pusA->refresh();
    expect($this->pusA->nama_istri)->toBe('Ny. Aisyah Nama Baru');
    // Territory MUST remain unchanged in Kecamatan A and Desa A
    expect($this->pusA->kecamatan_id)->toBe($this->kecamatanA->id);
    expect($this->pusA->desa_id)->toBe($this->desaA->id);
});

test('P0-02: admin cannot update PUS with invalid desa-kecamatan relation', function () {
    $this->actingAs($this->admin);

    // Admin tries to pair Kecamatan A with Desa B (which belongs to Kecamatan B)
    Livewire::test('pages::pus.index')
        ->call('openEditModal', $this->pusA->id)
        ->set('edit_nama_istri', 'Ny. Validasi Relasi')
        ->set('edit_nama_suami', 'Tn. Validasi')
        ->set('edit_tanggal_lahir_istri', now()->subYears(28)->format('Y-m-d'))
        ->set('edit_jumlah_anak_lahir_hidup', 1)
        ->set('edit_kecamatan_id', $this->kecamatanA->id)
        ->set('edit_desa_id', $this->desaB->id)
        ->call('updatePus')
        ->assertHasErrors(['edit_desa_id']);
});

/*
|--------------------------------------------------------------------------
| P0-03: Tutup IDOR pada detail antrean screening
|--------------------------------------------------------------------------
*/

test('P0-03: petugas lapangan cannot view screening detail from another territory', function () {
    // Create screening for PUS B (in territory B)
    $screeningB = Screening::create([
        'pus_profile_id' => $this->pusB->id,
        'user_id' => $this->petugasB->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 1,
        'p4_tujuan_reproduksi' => 1,
        'p5_risiko_kehamilan' => 1,
        'total_skor' => 9,
        'kategori' => \App\Enums\PriorityCategory::SEDANG,
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Edukasi Terjadwal',
        'urutan_antrean' => 3,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);

    // Petugas A tries to open detail of screeningB in priority queue
    $this->actingAs($this->petugasA);

    Livewire::test('pages::screening.priority-queue')
        ->call('openDetailModal', $screeningB->id)
        ->assertStatus(404);

    // Admin can open detail of screeningB
    $this->actingAs($this->admin);

    Livewire::test('pages::screening.priority-queue')
        ->call('openDetailModal', $screeningB->id)
        ->assertHasNoErrors()
        ->assertSet('showDetailModal', true);
});

test('P0-03: petugas lapangan cannot open or save intervention for screening from another territory', function () {
    $screeningB = Screening::create([
        'pus_profile_id' => $this->pusB->id,
        'user_id' => $this->petugasB->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 1,
        'p4_tujuan_reproduksi' => 1,
        'p5_risiko_kehamilan' => 1,
        'total_skor' => 9,
        'kategori' => \App\Enums\PriorityCategory::SEDANG,
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Edukasi Terjadwal',
        'urutan_antrean' => 3,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);

    $this->actingAs($this->petugasA);

    // Attempting to load screening from another territory returns 404
    Livewire::test('intervention-modal')
        ->call('loadScreening', $screeningB->id)
        ->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| P0-05: Hapus password default bersama (password123)
|--------------------------------------------------------------------------
*/

test('P0-05: user create modal does not prefill password with password123', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openCreateModal')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');
});

/*
|--------------------------------------------------------------------------
| P0-06: Endpoint OPcache reset tidak ada di direktori public
|--------------------------------------------------------------------------
*/

test('P0-06: public/opcache_reset.php does not exist', function () {
    expect(file_exists(public_path('opcache_reset.php')))->toBeFalse();
});
