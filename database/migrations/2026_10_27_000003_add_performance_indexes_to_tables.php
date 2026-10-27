<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pus_profiles', function (Blueprint $table) {
            $table->index('user_id');
            $table->index(['kecamatan_id', 'created_at']);
        });

        Schema::table('screenings', function (Blueprint $table) {
            $table->index(['tanggal_skrining', 'total_skor', 'is_mupar']);
            $table->index(['pus_profile_id', 'total_skor']);
        });

        Schema::table('interventions', function (Blueprint $table) {
            $table->index(['screening_id', 'tanggal_kunjungan']);
            $table->index(['tanggal_kunjungan', 'menggunakan_kb_modern']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interventions', function (Blueprint $table) {
            $table->dropIndex(['screening_id', 'tanggal_kunjungan']);
            $table->dropIndex(['tanggal_kunjungan', 'menggunakan_kb_modern']);
        });

        Schema::table('screenings', function (Blueprint $table) {
            $table->dropIndex(['tanggal_skrining', 'total_skor', 'is_mupar']);
            $table->dropIndex(['pus_profile_id', 'total_skor']);
        });

        Schema::table('pus_profiles', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['kecamatan_id', 'created_at']);
        });
    }
};
