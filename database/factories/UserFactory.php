<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hasFaker = function_exists('fake');

        return [
            'name' => $hasFaker ? fake()->name() : 'Pengguna Demo',
            'email' => $hasFaker ? fake()->unique()->safeEmail() : Str::random(10).'@simentilin.bangka.go.id',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::PETUGAS_LAPANGAN,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create as admin dinas.
     */
    public function adminDinas(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN_DINAS,
        ]);
    }

    /**
     * Create as petugas lapangan assigned to a specific kecamatan.
     */
    public function petugasLapangan(?int $kecamatanId = null, ?int $desaId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::PETUGAS_LAPANGAN,
            'kecamatan_id' => $kecamatanId,
            'desa_id' => $desaId,
        ]);
    }
}
