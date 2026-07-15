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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->enum('role', ['admin', 'mahasiswa', 'dosen']);
            $table->string('nama')->nullable();
            $table->string('nim')->nullable()->unique();   // khusus mahasiswa
            $table->string('nip')->nullable()->unique();   // khusus dosen
            $table->string('username')->unique();
            $table->string('prodi')->nullable();           // dosen & mahasiswa
            $table->string('email')->unique();
            $table->string('password');
            $table->string('foto')->nullable();
            $table->boolean('status')->default(true);      // aktif/tidak
            $table->string('tandatangan')->nullable();
            $table->rememberToken();
            $table->timestamps();                           // created_at, updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
