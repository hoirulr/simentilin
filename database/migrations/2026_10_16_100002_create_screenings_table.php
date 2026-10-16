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
        Schema::create('screenings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pus_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 5 Parameter Skor (masing-masing 0-3)
            $table->unsignedTinyInteger('p1_usia_istri');
            $table->unsignedTinyInteger('p2_jumlah_anak');
            $table->unsignedTinyInteger('p3_status_kontrasepsi');
            $table->unsignedTinyInteger('p4_tujuan_reproduksi');
            $table->unsignedTinyInteger('p5_risiko_kehamilan');

            // Aggregated results (computed by ScreeningCalculatorService)
            $table->unsignedTinyInteger('total_skor');
            $table->string('kategori');  // rendah, sedang, tinggi
            $table->boolean('is_mupar')->default(false);
            $table->string('prioritas_akhir');
            $table->unsignedTinyInteger('urutan_antrean');

            $table->date('tanggal_skrining');
            $table->text('catatan')->nullable();

            $table->timestamps();

            // Composite index for priority queue queries
            $table->index(['urutan_antrean', 'total_skor']);
            $table->index(['pus_profile_id', 'tanggal_skrining']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screenings');
    }
};
