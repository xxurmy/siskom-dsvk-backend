<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kartu_kolokiums', function (Blueprint $table) {
            $table->dropColumn(['tandatangandosen', 'statusparaf']);
        });

        Schema::table('kartu_seminars', function (Blueprint $table) {
            $table->dropColumn(['tandatangandosen', 'statusparaf']);
        });
    }

    public function down(): void
    {
        Schema::table('kartu_kolokiums', function (Blueprint $table) {
            $table->string('tandatangandosen')->nullable();
            $table->enum('statusparaf', ['signed', 'pending', 'absent'])->default('pending');
        });

        Schema::table('kartu_seminars', function (Blueprint $table) {
            $table->string('tandatangandosen')->nullable();
            $table->enum('statusparaf', ['signed', 'pending'])->default('pending');
        });
    }
};