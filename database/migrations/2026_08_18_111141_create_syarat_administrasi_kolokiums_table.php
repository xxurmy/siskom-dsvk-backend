<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syarat_administrasi_kolokiums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kolokium_id')
                ->unique() // 1 kolokium = 1 set syarat administrasi
                ->constrained('kolokiums')
                ->cascadeOnDelete();

            // ID folder gdrive khusus milik mahasiswa ini (nim_nama), dibuat sekali,
            // dipakai ulang untuk semua file di bawah supaya tidak bikin folder baru tiap upload
            $table->string('drive_folder_id')->nullable();

            // 1. Proposal yang sudah disetujui pembimbing (PDF max 50MB)
            $table->string('proposal_url')->nullable();
            $table->string('proposal_drive_id')->nullable();
            $table->timestamp('proposal_uploaded_at')->nullable();

            // 2. Bukti lunas SPP terbaru (image max 5MB)
            $table->string('bukti_spp_url')->nullable();
            $table->string('bukti_spp_drive_id')->nullable();
            $table->timestamp('bukti_spp_uploaded_at')->nullable();

            // 3. Transkrip nilai / keterangan min 110 SKS, IPK > 2.00, tanpa BL (PDF max 5MB)
            $table->string('transkrip_url')->nullable();
            $table->string('transkrip_drive_id')->nullable();
            $table->timestamp('transkrip_uploaded_at')->nullable();

            // 4. Bukti telah mengikuti kolokium minimal 10 kali (image max 5MB)
            $table->string('bukti_kolokium_url')->nullable();
            $table->string('bukti_kolokium_drive_id')->nullable();
            $table->timestamp('bukti_kolokium_uploaded_at')->nullable();

            // 5. Makalah kolokium sesuai format (PDF max 50MB)
            $table->string('makalah_url')->nullable();
            $table->string('makalah_drive_id')->nullable();
            $table->timestamp('makalah_uploaded_at')->nullable();

            // Status verifikasi oleh admin
            $table->enum('status', ['belum_lengkap', 'menunggu_verifikasi', 'lengkap', 'ditolak'])
                ->default('belum_lengkap');
            $table->text('catatan_admin')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syarat_administrasi_kolokiums');
    }
};