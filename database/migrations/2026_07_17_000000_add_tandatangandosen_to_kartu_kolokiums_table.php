<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kartu_kolokiums', function (Blueprint $table) {
            $table->string('tandatangandosen')->nullable()->after('nimforum');
        });
    }

    public function down(): void
    {
        Schema::table('kartu_kolokiums', function (Blueprint $table) {
            $table->dropColumn('tandatangandosen');
        });
    }
};