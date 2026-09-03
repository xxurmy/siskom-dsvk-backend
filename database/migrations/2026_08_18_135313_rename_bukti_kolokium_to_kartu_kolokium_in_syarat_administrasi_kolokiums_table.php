<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('syarat_administrasi_kolokiums', function (Blueprint $table) {
            $table->renameColumn('bukti_kolokium_url', 'kartu_kolokium_url');
            $table->renameColumn('bukti_kolokium_drive_id', 'kartu_kolokium_drive_id');
            $table->renameColumn('bukti_kolokium_uploaded_at', 'kartu_kolokium_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('syarat_administrasi_kolokiums', function (Blueprint $table) {
            $table->renameColumn('kartu_kolokium_url', 'bukti_kolokium_url');
            $table->renameColumn('kartu_kolokium_drive_id', 'bukti_kolokium_drive_id');
            $table->renameColumn('kartu_kolokium_uploaded_at', 'bukti_kolokium_uploaded_at');
        });
    }
};