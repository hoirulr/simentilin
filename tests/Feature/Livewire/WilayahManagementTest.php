<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\PusProfile;
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

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => 1,
        'desa_id' => 1,
    ]);
});

test('admin can access master wilayah page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.wilayah'));

    $response->assertOk();
    $response->assertSee('Master Wilayah & Pemetaan Sasaran');
    $response->assertSee('Sungailiat');
    $response->assertSee('Belinyu');
    $response->assertSee('Merawang');
    $response->assertSee('Mendo Barat');
    $response->assertSee('Pemali');
    $response->assertSee('Bakam');
    $response->assertSee('Riau Silip');
    $response->assertSee('Puding Besar');
});

test('petugas lapangan cannot access master wilayah page', function () {
    $this->actingAs($this->petugas);

    $response = $this->get(route('admin.wilayah'));

    $response->assertForbidden();
});

test('can switch between kecamatan tab and desa tab', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.wilayah')
        ->assertSet('activeTab', 'kecamatan')
        ->call('setTab', 'desa')
        ->assertSet('activeTab', 'desa')
        ->assertSee('Daftar Desa / Kelurahan');
});

test('admin can create a new desa', function () {
    $this->actingAs($this->admin);

    $kecamatan = Kecamatan::where('name', 'Sungailiat')->first();

    Livewire::test('pages::admin.wilayah')
        ->call('openCreateDesaModal')
        ->set('desa_kecamatan_id', $kecamatan->id)
        ->set('desa_name', 'Desa Rebo Baru')
        ->set('desa_code', '19.01.01.2099')
        ->call('saveDesa')
        ->assertHasNoErrors()
        ->assertSet('showDesaModal', false);

    $this->assertDatabaseHas('desas', [
        'kecamatan_id' => $kecamatan->id,
        'name' => 'Desa Rebo Baru',
        'code' => '19.01.01.2099',
    ]);
});

test('validates required fields and code uniqueness when creating desa', function () {
    $this->actingAs($this->admin);

    $existingDesa = Desa::first();

    Livewire::test('pages::admin.wilayah')
        ->call('openCreateDesaModal')
        ->set('desa_kecamatan_id', null)
        ->set('desa_name', '')
        ->set('desa_code', $existingDesa->code) // Duplicate
        ->call('saveDesa')
        ->assertHasErrors(['desa_kecamatan_id', 'desa_name', 'desa_code']);
});

test('admin can edit desa name and code', function () {
    $this->actingAs($this->admin);

    $desa = Desa::first();

    Livewire::test('pages::admin.wilayah')
        ->call('openEditDesaModal', $desa->id)
        ->assertSet('editingDesaId', $desa->id)
        ->assertSet('desa_name', $desa->name)
        ->set('desa_name', $desa->name.' Updated')
        ->call('saveDesa')
        ->assertHasNoErrors()
        ->assertSet('showDesaModal', false);

    expect($desa->fresh()->name)->toBe($desa->name.' Updated');
});

test('admin can edit kecamatan details', function () {
    $this->actingAs($this->admin);

    $kecamatan = Kecamatan::where('name', 'Sungailiat')->first();

    Livewire::test('pages::admin.wilayah')
        ->call('openEditKecamatanModal', $kecamatan->id)
        ->assertSet('editingKecamatanId', $kecamatan->id)
        ->assertSet('kecamatan_name', $kecamatan->name)
        ->set('kecamatan_name', 'Sungailiat Kota')
        ->call('saveKecamatan')
        ->assertHasNoErrors()
        ->assertSet('showKecamatanModal', false);

    expect($kecamatan->fresh()->name)->toBe('Sungailiat Kota');
});

test('cannot delete desa if it has registered PUS profiles', function () {
    $this->actingAs($this->admin);

    $desa = Desa::first();

    PusProfile::create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $desa->kecamatan_id,
        'desa_id' => $desa->id,
        'nama_istri' => 'Siti Aisyah',
        'nama_suami' => 'Ahmad Dani',
        'tanggal_lahir_istri' => '1995-05-15',
        'jumlah_anak_lahir_hidup' => 2,
    ]);

    Livewire::test('pages::admin.wilayah')
        ->call('confirmDeleteDesa', $desa->id)
        ->assertSet('showDeleteDesaModal', false);

    $this->assertDatabaseHas('desas', ['id' => $desa->id]);
});

test('can delete empty desa without PUS profiles', function () {
    $this->actingAs($this->admin);

    $emptyDesa = Desa::create([
        'kecamatan_id' => 1,
        'name' => 'Desa Percobaan Hapus',
        'code' => '19.01.01.9999',
    ]);

    Livewire::test('pages::admin.wilayah')
        ->call('confirmDeleteDesa', $emptyDesa->id)
        ->assertSet('showDeleteDesaModal', true)
        ->call('deleteDesa')
        ->assertSet('showDeleteDesaModal', false);

    $this->assertDatabaseMissing('desas', ['id' => $emptyDesa->id]);
});

test('can search and filter desas by kecamatan', function () {
    $this->actingAs($this->admin);

    $sungailiatKec = Kecamatan::where('name', 'Sungailiat')->first();
    $belinyuKec = Kecamatan::where('name', 'Belinyu')->first();

    Livewire::test('pages::admin.wilayah')
        ->call('setTab', 'desa')
        ->set('filter_kecamatan_id', $sungailiatKec->id)
        ->assertSee('Sungailiat')
        ->set('search', 'Kenanga')
        ->assertSee('Kenanga')
        ->set('search', 'Kota Kapur') // In Mendo Barat
        ->assertSee('Tidak ada desa/kelurahan yang cocok');
});
