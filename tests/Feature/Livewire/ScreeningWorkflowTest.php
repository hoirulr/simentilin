<?php

use App\Enums\PriorityCategory;
use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->kecamatan = Kecamatan::first();
    $this->desa = Desa::where('kecamatan_id', $this->kecamatan->id)->first();

    $this->petugas = User::factory()->create([
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
    ]);
});

test('pus index component renders and can register new PUS with MUPAR detection', function () {
    $this->actingAs($this->petugas);

    Livewire::test('pages::pus.index')
        ->assertOk()
        ->assertSee('Data Profil Pasangan Usia Subur (PUS)')
        ->call('openCreateModal')
        ->set('nama_istri', 'Siti Rahma')
        ->set('nama_suami', 'Budi Santoso')
        ->set('tanggal_lahir_istri', now()->subYears(25)->format('Y-m-d'))
        ->set('jumlah_anak_lahir_hidup', 1)
        ->set('new_kecamatan_id', $this->kecamatan->id)
        ->set('new_desa_id', $this->desa->id)
        ->set('rt_rw', 'RT 01 / RW 02')
        ->set('alamat', 'Jl. Merdeka Sungailiat')
        ->call('savePus')
        ->assertHasNoErrors()
        ->assertSee('Siti Rahma');

    $this->assertDatabaseHas('pus_profiles', [
        'nama_istri' => 'Siti Rahma',
        'nama_suami' => 'Budi Santoso',
        'jumlah_anak_lahir_hidup' => 1,
    ]);
});

test('screening calculator calculates P1 and P2 automatically from PUS profile', function () {
    $this->actingAs($this->petugas);

    // Create a MUPAR PUS: 28 years old (P1 = 3), 1 child (P2 = 3)
    $pus = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Dewi Sartika',
        'tanggal_lahir_istri' => now()->subYears(28)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    Livewire::test('pages::screening.create', ['pus_id' => $pus->id])
        ->assertOk()
        ->assertSet('pus_id', $pus->id)
        ->assertSet('usia_istri', 28)
        ->assertSet('jumlah_anak', 1)
        ->assertSet('p1', 3) // Auto calculated: <= 35 th → 3
        ->assertSet('p2', 3) // Auto calculated: 0-2 anak → 3
        ->assertSet('is_mupar', true) // Auto determined: 28y <= 35 && 1 child <= 2
        ->assertSee('Dewi Sartika')
        ->assertSee('PUS MUPAR');
});

test('screening calculator updates total score 0-15 and MUPAR priority real-time', function () {
    $this->actingAs($this->petugas);

    // MUPAR PUS: P1 = 3 (age 25), P2 = 3 (1 child)
    $pus = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'tanggal_lahir_istri' => now()->subYears(25)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 1,
    ]);

    // Initial state: P1=3, P2=3, P3=0, P4=0, P5=0 -> Total: 6
    $component = Livewire::test('pages::screening.create', ['pus_id' => $pus->id]);
    $component->assertViewHas('total_skor', 6)
        ->assertViewHas('computed_is_mupar', true)
        ->assertViewHas('prioritas_label', 'Monitoring / Edukasi Rutin')
        ->assertViewHas('urutan_antrean', 5);

    // Update P3, P4, P5 to max values (3 each) -> Total: 3+3+3+3+3 = 15
    $component->set('p3', 3)
        ->set('p4', 3)
        ->set('p5', 3)
        ->assertViewHas('total_skor', 15)
        ->assertViewHas('computed_is_mupar', true)
        ->assertViewHas('kategori', PriorityCategory::TINGGI)
        ->assertViewHas('prioritas_label', 'Prioritas Utama')
        ->assertViewHas('urutan_antrean', 1);

    // Save screening
    $component->call('saveScreening')
        ->assertHasNoErrors()
        ->assertRedirect('/screening/priority-queue');

    $this->assertDatabaseHas('screenings', [
        'pus_profile_id' => $pus->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 3,
        'p3_status_kontrasepsi' => 3,
        'p4_tujuan_reproduksi' => 3,
        'p5_risiko_kehamilan' => 3,
        'total_skor' => 15,
        'kategori' => PriorityCategory::TINGGI->value,
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
    ]);
});

test('priority queue orders Prioritas Utama (Skor Tinggi + MUPAR) first at the top', function () {
    $this->actingAs($this->petugas);

    // 1. Create a Low Priority PUS (Skor 4, Urutan 5)
    $pusLow = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Zulfa Low',
    ]);
    Screening::factory()->create([
        'pus_profile_id' => $pusLow->id,
        'user_id' => $this->petugas->id,
        'total_skor' => 4,
        'kategori' => PriorityCategory::RENDAH->value,
        'is_mupar' => false,
        'prioritas_akhir' => 'Monitoring / Edukasi Rutin',
        'urutan_antrean' => 5,
        'tanggal_skrining' => now()->subDays(5)->format('Y-m-d'),
    ]);

    // 2. Create a High Score Non-MUPAR PUS (Skor 13, Urutan 2: Prioritas Tinggi)
    $pusHighNonMupar = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Berta High Non-Mupar',
    ]);
    Screening::factory()->create([
        'pus_profile_id' => $pusHighNonMupar->id,
        'user_id' => $this->petugas->id,
        'total_skor' => 13,
        'kategori' => PriorityCategory::TINGGI->value,
        'is_mupar' => false,
        'prioritas_akhir' => 'Prioritas Tinggi',
        'urutan_antrean' => 2,
        'tanggal_skrining' => now()->subDays(2)->format('Y-m-d'),
    ]);

    // 3. Create a High Score MUPAR PUS (Skor 14, Urutan 1: Prioritas Utama)
    $pusUtama = PusProfile::factory()->create([
        'user_id' => $this->petugas->id,
        'kecamatan_id' => $this->kecamatan->id,
        'desa_id' => $this->desa->id,
        'nama_istri' => 'Aisyah Prioritas Utama',
    ]);
    Screening::factory()->create([
        'pus_profile_id' => $pusUtama->id,
        'user_id' => $this->petugas->id,
        'total_skor' => 14,
        'kategori' => PriorityCategory::TINGGI->value,
        'is_mupar' => true,
        'prioritas_akhir' => 'Prioritas Utama',
        'urutan_antrean' => 1,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);

    // Test Priority Queue Livewire component
    $test = Livewire::test('pages::screening.priority-queue')
        ->assertOk()
        ->assertSee('Antrean Prioritas Kunjungan PKB / PLKB');

    // Verify ordering in the returned queue: first item MUST be the Prioritas Utama (#1)
    $items = $test->viewData('queue');
    expect($items->first()->urutan_antrean)->toBe(1)
        ->and($items->first()->pusProfile->nama_istri)->toBe('Aisyah Prioritas Utama')
        ->and($items->first()->prioritas_akhir)->toBe('Prioritas Utama')
        ->and($items->first()->is_mupar)->toBeTrue();
});
