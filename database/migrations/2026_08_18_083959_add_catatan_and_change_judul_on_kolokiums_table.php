<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolokiums', function (Blueprint $table) {
            $table->string('judul', 500)->change();
            $table->text('catatan')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('kolokiums', function (Blueprint $table) {
            $table->string('judul', 255)->change();
            $table->dropColumn('catatan');
        });
    }
};