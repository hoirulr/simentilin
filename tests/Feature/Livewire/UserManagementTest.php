<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->kecamatan = Kecamatan::first();
    $this->desa = Desa::where('kecamatan_id', $this->kecamatan->id)->first();

    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
    ]);

    $this->petugas = User::factory()->create([
        'name' => 'PKB Sungailiat',
        'email' => 'pkb.sungailiat@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);
});

test('admin can access user management page', function () {
    $this->actingAs($this->admin);

    $response = $this->get(route('admin.users'));

    $response->assertOk();
    $response->assertSee('Manajemen Pengguna & Penugasan Wilayah');
    $response->assertSee($this->petugas->name);
    $response->assertSee($this->admin->name);
});

test('petugas lapangan cannot access user management page', function () {
    $this->actingAs($this->petugas);

    $response = $this->get(route('admin.users'));

    $response->assertForbidden();
});

test('admin can create a new petugas lapangan user with territory assignment', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openCreateModal')
        ->set('name', 'Budi Santoso, S.Sos')
        ->set('email', 'budi.santoso@simentilin.bangka.go.id')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('role', UserRole::PETUGAS_LAPANGAN->value)
        ->set('kecamatan_id', $this->kecamatan->id)
        ->set('desa_id', $this->desa->id)
        ->call('createUser')
        ->assertHasNoErrors()
        ->assertSet('showCreateModal', false);

    $this->assertDatabaseHas('users', [
        'name' => 'Budi Santoso, S.Sos',
        'email' => 'budi.santoso@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN->value,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);
});

test('admin can create a new admin dinas user without territory assignment', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openCreateModal')
        ->set('name', 'Staf Bidang KB Dinas')
        ->set('email', 'staf.kb@simentilin.bangka.go.id')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('role', UserRole::ADMIN_DINAS->value)
        ->call('createUser')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'Staf Bidang KB Dinas',
        'email' => 'staf.kb@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS->value,
        'kecamatan_id' => null,
        'desa_id' => null,
    ]);
});

test('validates required fields and email uniqueness when creating user', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openCreateModal')
        ->set('name', '')
        ->set('email', 'admin@simentilin.bangka.go.id') // Already exists
        ->set('password', 'short')
        ->set('password_confirmation', 'mismatch')
        ->call('createUser')
        ->assertHasErrors(['name', 'email', 'password']);
});

test('admin can update user profile and territory assignment', function () {
    $this->actingAs($this->admin);

    $otherKecamatan = Kecamatan::where('id', '!=', $this->kecamatan->id)->first();

    Livewire::test('pages::admin.users')
        ->call('openEditModal', $this->petugas->id)
        ->assertSet('editingUserId', $this->petugas->id)
        ->assertSet('name', $this->petugas->name)
        ->set('name', 'PKB Sungailiat Senior')
        ->set('kecamatan_id', $otherKecamatan->id)
        ->set('desa_id', null)
        ->call('updateUser')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    expect($this->petugas->fresh()->name)->toBe('PKB Sungailiat Senior');
    expect($this->petugas->fresh()->kecamatan_id)->toBe($otherKecamatan->id);
    expect($this->petugas->fresh()->desa_id)->toBeNull();
});

test('admin can reset user password', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openResetPasswordModal', $this->petugas->id)
        ->set('new_password', 'passwordBaru2026')
        ->set('new_password_confirmation', 'passwordBaru2026')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertSet('showResetPasswordModal', false);

    expect(Hash::check('passwordBaru2026', $this->petugas->fresh()->password))->toBeTrue();
});

test('admin can reset user password to default', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('openResetPasswordModal', $this->petugas->id)
        ->call('resetToDefaultPassword')
        ->assertSet('showResetPasswordModal', false);

    expect(Hash::check('password123', $this->petugas->fresh()->password))->toBeTrue();
});

test('admin cannot delete their own account', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('confirmDelete', $this->admin->id)
        ->assertSet('showDeleteModal', false);

    $this->assertDatabaseHas('users', ['id' => $this->admin->id]);
});

test('admin can delete another user account', function () {
    $this->actingAs($this->admin);

    $userToDelete = User::factory()->create([
        'name' => 'Petugas Magang',
        'email' => 'magang@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
    ]);

    Livewire::test('pages::admin.users')
        ->call('confirmDelete', $userToDelete->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteUser')
        ->assertSet('showDeleteModal', false);

    $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);
});

test('search and role filters filter user list properly', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->set('search', 'Sungailiat')
        ->assertSee('PKB Sungailiat')
        ->set('search', 'NonExistentPerson')
        ->assertSee('Tidak ada data pengguna yang sesuai')
        ->set('search', '')
        ->set('filter_role', UserRole::ADMIN_DINAS->value)
        ->assertSee($this->admin->name)
        ->assertDontSee($this->petugas->name);
});
