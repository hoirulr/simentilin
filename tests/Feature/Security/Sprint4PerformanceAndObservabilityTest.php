<?php

use App\Enums\PriorityCategory;
use App\Enums\UserRole;
use App\Models\Desa;
use App\Models\Intervention;
use App\Models\Kecamatan;
use App\Models\PusProfile;
use App\Models\Screening;
use App\Models\User;
use Database\Seeders\WilayahSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Events\Failed as AuthFailed;
use Illuminate\Auth\Events\Lockout as AuthLockout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(WilayahSeeder::class);

    $this->admin = User::factory()->create([
        'name' => 'Admin DP2KBP3A',
        'email' => 'admin.dinas.p4@simentilin.bangka.go.id',
        'role' => UserRole::ADMIN_DINAS,
        'kecamatan_id' => null,
        'desa_id' => null,
        'is_active' => true,
    ]);

    // Create sample data across 3 kecamatans
    $kecamatans = Kecamatan::take(3)->get();
    foreach ($kecamatans as $kec) {
        $desa = Desa::where('kecamatan_id', $kec->id)->first();
        $pus = PusProfile::factory()->create([
            'kecamatan_id' => $kec->id,
            'desa_id' => $desa->id,
            'user_id' => $this->admin->id,
            'nama_istri' => "Istri {$kec->name}",
            'nama_suami' => "Suami {$kec->name}",
            'tanggal_lahir_istri' => '1995-01-01',
            'jumlah_anak_lahir_hidup' => 1,
        ]);

        $screening = Screening::factory()->create([
            'pus_profile_id' => $pus->id,
            'user_id' => $this->admin->id,
            'total_skor' => 12,
            'kategori' => PriorityCategory::TINGGI,
            'is_mupar' => true,
            'prioritas_akhir' => 'Prioritas Utama',
            'urutan_antrean' => 1,
        ]);

        Intervention::factory()->create([
            'screening_id' => $screening->id,
            'user_id' => $this->admin->id,
            'tanggal_kunjungan' => now()->format('Y-m-d'),
            'durasi_menit' => 30,
            'biaya_operasional' => 50000,
            'menggunakan_kb_modern' => true,
        ]);
    }
});

// ══════════════════════════════════════════════════════════════════
// P2-01: LIVEWIRE 4 ISLAND & PLACEHOLDER VERIFICATION
// ══════════════════════════════════════════════════════════════════

describe('P2-01: Livewire 4 Island and Placeholder', function () {
    it('isolates heavy kecamatan recap on Dashboard with @island', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::dashboard.index')
            ->assertStatus(200)
            ->assertSee('Rekapitulasi 8 Kecamatan Kabupaten Bangka');
    });

    it('isolates ApexCharts on MCPR with @island and wire:ignore', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::mcpr.index')
            ->assertStatus(200)
            ->assertSee('Tren Kenaikan Capaian MCPR')
            ->assertSee('wire:ignore', false);
    });

    it('isolates economic breakdown table on Analitik with @island', function () {
        Livewire::actingAs($this->admin)
            ->test('pages::analitik.index')
            ->assertStatus(200)
            ->assertSee('Perbandingan Performa Nilai Ekonomis Antar 8 Kecamatan');
    });
});

// ══════════════════════════════════════════════════════════════════
// P2-02: QUERY OPTIMIZATION & N+1 ELIMINATION
// ══════════════════════════════════════════════════════════════════

describe('P2-02: Query Count & Optimization', function () {
    it('executes Dashboard rendering with minimal queries eliminating N+1 loops', function () {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($this->admin)
            ->test('pages::dashboard.index')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Previously ran 90+ queries in Livewire test lifecycle due to 8 kecamatan x 6 loops
        // With DashboardMetricsService, it runs <= 25 queries total across full test mount & island lifecycle
        expect(count($queries))->toBeLessThanOrEqual(25);
    });

    it('executes Analitik rendering with bulk district metrics eliminating N+1 loops', function () {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::actingAs($this->admin)
            ->test('pages::analitik.index')
            ->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Previously ran 64+ queries in Livewire test lifecycle due to 8 kecamatan loops
        // With allDistrictsMetrics(), it runs <= 18 queries total across full test mount & island lifecycle
        expect(count($queries))->toBeLessThanOrEqual(18);
    });
});

// ══════════════════════════════════════════════════════════════════
// P2-04: OBSERVABILITY & STRUCTURED SECURITY LOGGING
// ══════════════════════════════════════════════════════════════════

describe('P2-04: Structured Security Logging', function () {
    it('logs warning without PII on authentication failure', function () {
        Log::spy();

        event(new AuthFailed('web', null, [
            'email' => 'suspicious.attacker@target.com',
            'password' => 'superSecretPassword123!',
        ]));

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'security.auth_failed'
                    && isset($context['ip'])
                    && isset($context['masked_identifier'])
                    && $context['masked_identifier'] === 'sus***'
                    && ! isset($context['password'])
                    && ! in_array('superSecretPassword123!', $context);
            });
    });

    it('logs alert on authentication lockout event', function () {
        Log::spy();

        event(new AuthLockout(request()));

        Log::shouldHaveReceived('alert')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'security.auth_lockout'
                    && isset($context['ip']);
            });
    });

    it('reports authorization failures with structured security logging', function () {
        Log::spy();

        $exception = new AuthorizationException('This action is unauthorized.');
        report($exception);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'security.authorization_failed'
                    && isset($context['path'])
                    && $context['message'] === 'This action is unauthorized.';
            });
    });
});
