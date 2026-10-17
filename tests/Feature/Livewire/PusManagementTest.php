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

    $this->kecamatan = Kecamatan::where('name', 'Sungailiat')->first() ?? Kecamatan::first();
    $this->desa = Desa::where('kecamatan_id', $this->kecamatan->id)->first();

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);

    $this->pus = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Nur Fatimah',
        'nama_suami' => 'Rian Hidayat',
        'tanggal_lahir_istri' => '1998-03-25',
        'jumlah_anak_lahir_hidup' => 2,
        'alamat' => 'Jl. Pemuda No. 10',
    ]);
});

test('authenticated petugas can access pus index page', function () {
    $this->actingAs($this->petugas);

    $response = $this->get(route('pus.index'));

    $response->assertOk();
    $response->assertSee('Nur Fatimah');
    $response->assertSee('Rian Hidayat');
});

test('petugas can open edit modal and update pus profile data', function () {
    $this->actingAs($this->petugas);

    Livewire::test('pages::pus.index')
        ->call('openEditModal', $this->pus->id)
        ->assertSet('showEditModal', true)
        ->assertSet('edit_nama_istri', 'Nur Fatimah')
        ->set('edit_nama_istri', 'Nur Fatimah Az-Zahra')
        ->set('edit_jumlah_anak_lahir_hidup', 3)
        ->call('updatePus')
        ->assertSet('showEditModal', false);

    $this->assertDatabaseHas('pus_profiles', [
        'id' => $this->pus->id,
        'nama_istri' => 'Nur Fatimah Az-Zahra',
        'jumlah_anak_lahir_hidup' => 3,
    ]);
});

test('petugas can open detail modal and view timeline history', function () {
    $this->actingAs($this->petugas);

    // Create a screening with intervention for this PUS
    $screening = Screening::factory()->create([
        'pus_profile_id' => $this->pus->id,
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
        'catatan' => 'Sasaran membutuhkan KIE segera',
    ]);

    Intervention::factory()->create([
        'screening_id' => $screening->id,
        'user_id' => $this->petugas->id,
        'tanggal_kunjungan' => now()->format('Y-m-d'),
        'jenis_tindakan' => 'KIE / Konseling KB Segera',
        'durasi_menit' => 40,
        'biaya_operasional' => 35000,
        'menggunakan_kb_modern' => true,
        'metode_kb' => 'Implan / Susuk',
    ]);

    Livewire::test('pages::pus.index')
        ->call('openDetailModal', $this->pus->id)
        ->assertSet('showDetailModal', true)
        ->assertSee('Timeline Rekam Jejak Skrining')
        ->assertSee('Prioritas Utama')
        ->assertSee('Implan / Susuk')
        ->call('closeDetailModal')
        ->assertSet('showDetailModal', false);
});

test('petugas cannot edit PUS owned by another petugas in the same kecamatan', function () {
    $petugasB = User::factory()->create([
        'name' => 'PKB Sungailiat 2',
        'email' => 'pkb2.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);

    $this->actingAs($petugasB);

    Livewire::test('pages::pus.index')
        ->call('openEditModal', $this->pus->id)
        ->assertForbidden();
});

test('petugas cannot delete PUS owned by another petugas', function () {
    $petugasB = User::factory()->create([
        'name' => 'PKB Sungailiat 2',
        'email' => 'pkb2.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);

    $this->actingAs($petugasB);

    Livewire::test('pages::pus.index')
        ->call('deletePus', $this->pus->id)
        ->assertForbidden();
});

test('admin dinas can edit and delete PUS created by any petugas', function () {
    $admin = User::factory()->adminDinas()->create([
        'email' => 'admin@simentilin.bangka.go.id',
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::pus.index')
        ->call('openEditModal', $this->pus->id)
        ->assertOk()
        ->assertSet('showEditModal', true);

    Livewire::test('pages::pus.index')
        ->call('deletePus', $this->pus->id)
        ->assertOk();

    $this->assertSoftDeleted('pus_profiles', ['id' => $this->pus->id]);
});
