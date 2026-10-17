<?php

namespace Database\Factories;

use App\Models\Intervention;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Intervention>
 */
class InterventionFactory extends Factory
{
    protected $model = Intervention::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'screening_id' => Screening::factory(),
            'user_id' => User::factory(),
            'tanggal_kunjungan' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'jenis_tindakan' => 'Konseling KB dan Tindak Lanjut',
            'deskripsi_tindakan' => fake()->paragraph(),
            'durasi_menit' => fake()->numberBetween(15, 60),
            'biaya_operasional' => fake()->randomElement([50000, 75000, 100000, 150000]),
            'menggunakan_kb_modern' => true,
            'metode_kb' => fake()->randomElement(['IUD', 'Implan', 'Suntik', 'Pil', 'Kondom', 'MOW', 'MOP']),
            'catatan' => fake()->sentence(),
        ];
    }

    /**
     * State for non-modern KB intervention.
     */
    public function withoutModernKb(): static
    {
        return $this->state(fn (array $attributes) => [
            'menggunakan_kb_modern' => false,
            'metode_kb' => null,
        ]);
    }
}
