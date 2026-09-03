<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_kolokiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kolokium_id')->constrained('kolokiums')->cascadeOnDelete();
            $table->foreignId('pemrasaran_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('peserta_kolokium_id')->constrained('peserta_kolokiums')->cascadeOnDelete();
            $table->foreignId('forum_id')->constrained('users')->cascadeOnDelete();
            $table->date('tanggal')->nullable();
            $table->string('waktu')->nullable();
            $table->string('namapemrasaran')->nullable();
            $table->string('nimpemrasaran')->nullable();
            $table->string('prodi')->nullable();
            $table->string('moderator')->nullable();
            $table->string('namaforum')->nullable();
            $table->string('nimforum')->nullable();
            $table->enum('statusparaf', ['signed', 'pending'])->default('pending');
            $table->timestamps();

            $table->unique('peserta_kolokium_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_kolokiums');
    }
};
