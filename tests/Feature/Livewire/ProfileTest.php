<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->user = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi.santoso@simentilin.bangka.go.id',
        'password' => 'password123',
        'role' => UserRole::PETUGAS_LAPANGAN,
    ]);
});

test('authenticated user can access profile page', function () {
    $this->actingAs($this->user);

    $response = $this->get(route('profile'));

    $response->assertOk();
    $response->assertSee('Budi Santoso');
    $response->assertSee('budi.santoso@simentilin.bangka.go.id');
    $response->assertSee('Informasi Akun');
    $response->assertSee('Keamanan & Kata Sandi');
});

test('unauthenticated user cannot access profile page', function () {
    $response = $this->get(route('profile'));
    $response->assertRedirect(route('login'));
});

test('user can update profile information', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::profile.index')
        ->set('name', 'Budi Santoso, S.Sos')
        ->set('email', 'budi.baru@simentilin.bangka.go.id')
        ->call('updateProfile')
        ->assertHasNoErrors()
        ->assertSee('Data profil Anda berhasil diperbarui!');

    $this->assertDatabaseHas('users', [
        'id' => $this->user->id,
        'name' => 'Budi Santoso, S.Sos',
        'email' => 'budi.baru@simentilin.bangka.go.id',
    ]);
});

test('user can change password successfully with valid credentials', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::profile.index')
        ->set('current_password', 'password123')
        ->set('password', 'newsecretpass123')
        ->set('password_confirmation', 'newsecretpass123')
        ->call('updatePassword')
        ->assertHasNoErrors()
        ->assertSee('Kata sandi akun Anda berhasil diperbarui!');

    $this->user->refresh();
    $this->assertTrue(Hash::check('newsecretpass123', $this->user->password));
});

test('fails to change password if current password is wrong', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::profile.index')
        ->set('current_password', 'wrongpassword')
        ->set('password', 'newsecretpass123')
        ->set('password_confirmation', 'newsecretpass123')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});
