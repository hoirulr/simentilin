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
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('screening_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Tindakan penjangkauan
            $table->date('tanggal_kunjungan');
            $table->string('jenis_tindakan');
            $table->text('deskripsi_tindakan')->nullable();

            // Durasi & Biaya untuk kalkulasi Nilai Ekonomis (PIM III)
            $table->unsignedInteger('durasi_menit')->default(0);
            $table->decimal('biaya_operasional', 12, 2)->default(0);

            // Hasil intervensi KB
            $table->boolean('menggunakan_kb_modern')->default(false);
            $table->string('metode_kb')->nullable();

            $table->text('catatan')->nullable();
            $table->timestamps();

            // Index for economic metrics aggregation queries
            $table->index(['user_id', 'tanggal_kunjungan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};
