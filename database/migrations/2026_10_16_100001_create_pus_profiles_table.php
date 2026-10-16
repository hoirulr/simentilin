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
        Schema::create('pus_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kecamatan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('desa_id')->constrained()->cascadeOnDelete();

            // Identitas PUS
            $table->string('nama_istri');
            $table->string('nama_suami');
            $table->date('tanggal_lahir_istri');
            $table->unsignedTinyInteger('jumlah_anak_lahir_hidup')->default(0);

            // Alamat detail
            $table->string('rt_rw')->nullable();
            $table->text('alamat')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common query patterns: filtering by territory
            $table->index(['kecamatan_id', 'desa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pus_profiles');
    }
};
