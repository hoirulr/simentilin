<?php

namespace Database\Factories;

use App\Models\PusProfile;
use App\Models\Screening;
use App\Services\ScreeningCalculatorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Screening>
 */
class ScreeningFactory extends Factory
{
    protected $model = Screening::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pusProfile = PusProfile::factory()->create();
        $service = new ScreeningCalculatorService;

        $p3 = fake()->numberBetween(0, 3);
        $p4 = fake()->numberBetween(0, 3);
        $p5 = fake()->numberBetween(0, 3);

        $calc = $service->calculate(
            $pusProfile->usiaIstri(),
            $pusProfile->jumlah_anak_lahir_hidup,
            $p3,
            $p4,
            $p5,
        );

        return [
            'pus_profile_id' => $pusProfile->id,
            'user_id' => $pusProfile->user_id,
            'p1_usia_istri' => $calc['p1_usia_istri'],
            'p2_jumlah_anak' => $calc['p2_jumlah_anak'],
            'p3_status_kontrasepsi' => $p3,
            'p4_tujuan_reproduksi' => $p4,
            'p5_risiko_kehamilan' => $p5,
            'total_skor' => $calc['total_skor'],
            'kategori' => $calc['kategori']->value,
            'is_mupar' => $calc['is_mupar'],
            'prioritas_akhir' => $calc['prioritas_akhir'],
            'urutan_antrean' => $calc['urutan_antrean'],
            'tanggal_skrining' => now()->format('Y-m-d'),
            'catatan' => fake()->sentence(),
        ];
    }
}
