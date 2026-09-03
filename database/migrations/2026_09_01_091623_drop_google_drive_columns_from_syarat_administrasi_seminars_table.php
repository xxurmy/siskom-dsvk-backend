<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('syarat_administrasi_seminars', function (Blueprint $table) {
        $table->dropColumn([
            'drive_folder_id',
            'proposal_drive_id',
            'bukti_spp_drive_id',
            'transkrip_drive_id',
            'kartu_seminar_drive_id',
            'makalah_drive_id',
        ]);
    });
}
};
