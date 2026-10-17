<?php

use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Livewire\Livewire;

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

test('login screen can be rendered successfully', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertSee('Autentikasi Peran SI-MENTILIN');
    $response->assertSee('Petugas Lapangan');
    $response->assertSee('Admin Dinas');
});

test('petugas lapangan can authenticate with valid credentials', function () {
    Livewire::test('pages::auth.login')
        ->set('email', 'pkb.sungailiat@simentilin.bangka.go.id')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->petugas);
    expect($this->petugas->isPetugasLapangan())->toBeTrue();
});

test('admin dinas can authenticate with valid credentials', function () {
    Livewire::test('pages::auth.login')
        ->set('email', 'admin@simentilin.bangka.go.id')
        ->set('password', 'password')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($this->admin);
    expect($this->admin->isAdminDinas())->toBeTrue();
});

test('demo role prefill button sets proper email and role for petugas and admin', function () {
    Livewire::test('pages::auth.login')
        ->call('fillDemo', 'petugas_lapangan')
        ->assertSet('email', 'pkb.sungailiat@simentilin.bangka.go.id')
        ->assertSet('password', 'password')
        ->assertSet('selectedRole', 'petugas_lapangan')
        ->call('fillDemo', 'admin_dinas')
        ->assertSet('email', 'admin@simentilin.bangka.go.id')
        ->assertSet('password', 'password')
        ->assertSet('selectedRole', 'admin_dinas');
});

test('cannot authenticate with invalid password', function () {
    Livewire::test('pages::auth.login')
        ->set('email', 'pkb.sungailiat@simentilin.bangka.go.id')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('unauthenticated users are redirected to login for protected routes', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('pus.index'))->assertRedirect(route('login'));
    $this->get(route('screening.create'))->assertRedirect(route('login'));
    $this->get(route('screening.priority-queue'))->assertRedirect(route('login'));
});

test('role middleware permits admin_dinas and forbids petugas_lapangan for admin routes', function () {
    // Petugas Lapangan should get 403 Forbidden
    $this->actingAs($this->petugas);
    $response = $this->get(route('admin.users'));
    $response->assertForbidden();

    // Admin Dinas should pass and render the page successfully
    $this->actingAs($this->admin);
    $responseAdmin = $this->get(route('admin.users'));
    $responseAdmin->assertOk();
});

test('authenticated user can logout and session is invalidated', function () {
    $this->actingAs($this->petugas);
    $this->assertAuthenticated();

    $response = $this->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
