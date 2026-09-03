<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
    {
        Schema::create('kolokiums', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mahasiswa_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();

            // Snapshot data mahasiswa saat kolokium dibuat
            $table->string('nama');
            $table->string('nim');
            $table->string('prodi');

            // Snapshot ringkas nama pembimbing (gabungan, untuk tampilan cepat tanpa join)
            $table->string('namadosenpembimbing')->nullable();

            $table->string('judul');
            $table->string('lokasi')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('waktu')->nullable();
            $table->string('namadosenmoderator')->nullable();
            $table->string('ruangan')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedInteger('jumlahforum')->default(0); // sum peserta_kolokium status hadir

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolokiums');
    }
};
