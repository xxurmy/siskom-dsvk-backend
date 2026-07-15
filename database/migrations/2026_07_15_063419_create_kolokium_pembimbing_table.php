<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kolokium_pembimbing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kolokium_id')->constrained('kolokiums')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan')->default(1); // 1 = pembimbing utama, 2 = pembimbing kedua
            $table->timestamps();

            $table->unique(['kolokium_id', 'user_id']); // cegah dosen dobel di kolokium yang sama
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolokium_pembimbing');
    }
};
