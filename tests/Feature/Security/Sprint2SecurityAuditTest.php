<?php

use App\Enums\PriorityCategory;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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
        'name' => 'Petugas Lapangan A',
        'email' => 'petugas.a.p1@simentilin.bangka.go.id',
        'role' => UserRole::PETUGAS_LAPANGAN,
        'kecamatan_id' => $this->kecamatanA->id,
        'desa_id' => $this->desaA->id,
        'is_active' => true,
    ]);

    // Admin Dinas
    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin.dinas.p1@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
        'kecamatan_id' => null,
        'desa_id' => null,
        'is_active' => true,
    ]);

    // PUS in Wilayah A
    $this->pusA = PusProfile::factory()->create([
        'user_id' => $this->petugasA->id,
        'kecamatan_id' => $this->kecamatanA->id,
        'desa_id' => $this->desaA->id,
        'nama_istri' => 'Ny. Fatimah P1',
        'nama_suami' => 'Tn. Yusuf',
        'tanggal_lahir_istri' => now()->subYears(28)->format('Y-m-d'),
        'jumlah_anak_lahir_hidup' => 2,
    ]);

    // Screening for PUS A
    $this->screeningA = Screening::factory()->create([
        'pus_profile_id' => $this->pusA->id,
        'user_id' => $this->petugasA->id,
        'p1_usia_istri' => 3,
        'p2_jumlah_anak' => 2,
        'p3_status_kontrasepsi' => 3,
        'p4_tujuan_reproduksi' => 2,
        'p5_risiko_kehamilan' => 2,
        'total_skor' => 12,
        'kategori' => PriorityCategory::TINGGI->value,
        'is_mupar' => false,
        'prioritas_akhir' => 'Prioritas Tinggi',
        'urutan_antrean' => 2,
        'tanggal_skrining' => now()->format('Y-m-d'),
    ]);
});

/*
|--------------------------------------------------------------------------
| P1-01 & P1-02: Territory Integrity & User Policy Enforcement
|--------------------------------------------------------------------------
*/

test('P1-01: non-admin cannot access user management component or execute mutations', function () {
    $this->actingAs($this->petugasA);

    Livewire::test('pages::admin.users')
        ->assertStatus(403);
});

test('P1-02: admin cannot assign a desa that does not belong to the selected kecamatan', function () {
    $this->actingAs($this->admin);

    // desaB belongs to kecamatanB, but form sets kecamatanA
    Livewire::test('pages::admin.users')
        ->set('name', 'Petugas Baru Invalid')
        ->set('email', 'petugas.invalid@simentilin.bangka.go.id')
        ->set('role', UserRole::PETUGAS_LAPANGAN->value)
        ->set('password', 'KombinasiAman123!#')
        ->set('password_confirmation', 'KombinasiAman123!#')
        ->set('kecamatan_id', $this->kecamatanA->id)
        ->set('desa_id', $this->desaB->id) // INVALID cross-district combination
        ->call('createUser')
        ->assertHasErrors(['desa_id']);
});

/*
|--------------------------------------------------------------------------
| P1-01 & P1-03: Territory Management Policy & Protected Deletion
|--------------------------------------------------------------------------
*/

test('P1-01: non-admin cannot mutate master desa or kecamatan', function () {
    $this->actingAs($this->petugasA);

    Livewire::test('pages::admin.wilayah')
        ->assertStatus(403);
});

test('P1-03: admin cannot delete a desa that still has active PUS or assigned users', function () {
    $this->actingAs($this->admin);

    // desaA has pusA and petugasA
    Livewire::test('pages::admin.wilayah')
        ->call('confirmDeleteDesa', $this->desaA->id)
        ->assertSet('showDeleteDesaModal', false);

    $this->assertDatabaseHas('desas', ['id' => $this->desaA->id]);
});

test('P1-03: admin can delete an empty desa without records', function () {
    $this->actingAs($this->admin);

    $emptyDesa = Desa::create([
        'kecamatan_id' => $this->kecamatanA->id,
        'name' => 'Desa Kosong Baru',
        'code' => '19.01.99.2001',
    ]);

    Livewire::test('pages::admin.wilayah')
        ->call('confirmDeleteDesa', $emptyDesa->id)
        ->assertSet('showDeleteDesaModal', true)
        ->call('deleteDesa')
        ->assertSet('showDeleteDesaModal', false);

    $this->assertDatabaseMissing('desas', ['id' => $emptyDesa->id]);
});

/*
|--------------------------------------------------------------------------
| P1-03: Historical Record Preservation on User Deletion & Status Toggle
|--------------------------------------------------------------------------
*/

test('P1-03: admin cannot delete a user who owns historical operational data', function () {
    $this->actingAs($this->admin);

    // petugasA owns pusA and screeningA
    Livewire::test('pages::admin.users')
        ->call('confirmDelete', $this->petugasA->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteUser')
        ->assertSet('showDeleteModal', false);

    // Ensure petugasA is not deleted
    $this->assertDatabaseHas('users', [
        'id' => $this->petugasA->id,
        'deleted_at' => null,
    ]);
});

test('P1-03: admin can toggle user active status', function () {
    $this->actingAs($this->admin);

    Livewire::test('pages::admin.users')
        ->call('toggleUserStatus', $this->petugasA->id);

    expect($this->petugasA->fresh()->is_active)->toBeFalse();

    Livewire::test('pages::admin.users')
        ->call('toggleUserStatus', $this->petugasA->id);

    expect($this->petugasA->fresh()->is_active)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| P1-04: Domain Validation on Interventions
|--------------------------------------------------------------------------
*/

test('P1-04: intervention modal rejects future dates', function () {
    $this->actingAs($this->petugasA);

    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('tanggal_kunjungan', now()->addDay()->format('Y-m-d'))
        ->set('jenis_tindakan', 'KIE / Konseling KB')
        ->set('durasi_menit', 30)
        ->set('biaya_operasional', 25000)
        ->call('save')
        ->assertHasErrors(['tanggal_kunjungan']);
});

test('P1-04: intervention modal validates duration bounds (5 to 480 minutes)', function () {
    $this->actingAs($this->petugasA);

    // Reject under 5 minutes
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('durasi_menit', 4)
        ->call('save')
        ->assertHasErrors(['durasi_menit']);

    // Reject over 480 minutes (8 hours)
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('durasi_menit', 481)
        ->call('save')
        ->assertHasErrors(['durasi_menit']);
});

test('P1-04: intervention modal validates operational cost bounds (0 to 10,000,000)', function () {
    $this->actingAs($this->petugasA);

    // Reject negative cost
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('biaya_operasional', -5000)
        ->call('save')
        ->assertHasErrors(['biaya_operasional']);

    // Reject exorbitant cost
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('biaya_operasional', 10000001)
        ->call('save')
        ->assertHasErrors(['biaya_operasional']);
});

test('P1-04: intervention modal rejects invalid jenis_tindakan outside domain allowlist', function () {
    $this->actingAs($this->petugasA);

    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('jenis_tindakan', 'Tindakan Siluman Tidak Terdaftar')
        ->call('save')
        ->assertHasErrors(['jenis_tindakan']);
});

test('P1-04: intervention modal requires valid modern KB method when modern KB is checked', function () {
    $this->actingAs($this->petugasA);

    // Reject missing modern KB method
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('menggunakan_kb_modern', true)
        ->set('metode_kb', '')
        ->call('save')
        ->assertHasErrors(['metode_kb']);

    // Reject invalid modern KB method
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('menggunakan_kb_modern', true)
        ->set('metode_kb', 'Metode Tradisional Kalender')
        ->call('save')
        ->assertHasErrors(['metode_kb']);
});

/*
|--------------------------------------------------------------------------
| P1-03: Audit Trail Verification
|--------------------------------------------------------------------------
*/

test('P1-03: domain mutations create structured audit logs', function () {
    $this->actingAs($this->petugasA);

    // 1. Audit log on PUS creation
    Livewire::test('pages::pus.index')
        ->set('nama_istri', 'Ny. Audit Test')
        ->set('nama_suami', 'Tn. Audit Test')
        ->set('tanggal_lahir_istri', '1995-05-15')
        ->set('jumlah_anak_lahir_hidup', 1)
        ->call('savePus');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->petugasA->id,
        'action' => 'create_pus',
    ]);

    // 2. Audit log on Intervention creation
    Livewire::test('intervention-modal')
        ->dispatch('open-intervention-modal', screeningId: $this->screeningA->id)
        ->set('tanggal_kunjungan', now()->format('Y-m-d'))
        ->set('jenis_tindakan', 'KIE / Konseling KB')
        ->set('durasi_menit', 30)
        ->set('biaya_operasional', 25000)
        ->set('menggunakan_kb_modern', true)
        ->set('metode_kb', 'Implan / Susuk')
        ->call('save');

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $this->petugasA->id,
        'action' => 'create_intervention',
    ]);
});

/*
|--------------------------------------------------------------------------
| P1-07: Security Headers Verification
|--------------------------------------------------------------------------
*/

test('P1-07: web responses contain OWASP recommended security headers', function () {
    $response = $this->get('/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});
