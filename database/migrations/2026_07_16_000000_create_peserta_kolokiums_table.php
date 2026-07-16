<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_kolokiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kolokium_id')->constrained('kolokiums')->cascadeOnDelete();
            $table->foreignId('mahasiswa_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['hadir', 'batal'])->default('batal');
            $table->timestamps();

            $table->unique(['kolokium_id', 'mahasiswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_kolokiums');
    }
};
