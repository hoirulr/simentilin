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

test('root url is the default login screen and can be rendered successfully', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('SI-MENTILIN');
    $response->assertSee('Masuk ke Akun Anda');
    $response->assertSee('Alamat Email');
    $response->assertSee('Kata Sandi');
    $response->assertDontSee('Akses Resmi Sistem');

    // /login also redirects to root login route
    $this->get('/login')->assertRedirect(route('login'));
});

test('authenticated user accessing root url is redirected to dashboard', function () {
    $this->actingAs($this->petugas);

    $this->get('/')->assertRedirect(route('dashboard'));
    $this->get('/login')->assertRedirect(route('dashboard'));
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

test('login screen does not display demo prefill buttons', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    $response->assertDontSee('fillDemo');
    $response->assertDontSee('Pilih Cepat Akses Peran (Demo)');
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
