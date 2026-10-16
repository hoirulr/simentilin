<?php

namespace Database\Factories;

use App\Models\PusProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PusProfile>
 */
class PusProfileFactory extends Factory
{
    protected $model = PusProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kecamatan_id' => 1,
            'desa_id' => 1,
            'nama_istri' => fake()->name('female'),
            'nama_suami' => fake()->name('male'),
            'tanggal_lahir_istri' => fake()->dateTimeBetween('-45 years', '-20 years')->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => fake()->numberBetween(0, 5),
            'rt_rw' => 'RT '.fake()->numberBetween(1, 10).' / RW '.fake()->numberBetween(1, 5),
            'alamat' => fake()->streetAddress(),
        ];
    }

    /**
     * MUPAR state: young wife (<= 35 years) and low parity (<= 2 children).
     */
    public function mupar(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_lahir_istri' => now()->subYears(26)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 1,
        ]);
    }

    /**
     * Non-MUPAR state: older wife (> 35 years) or many children (> 2).
     */
    public function nonMupar(): static
    {
        return $this->state(fn (array $attributes) => [
            'tanggal_lahir_istri' => now()->subYears(42)->format('Y-m-d'),
            'jumlah_anak_lahir_hidup' => 4,
        ]);
    }
}
