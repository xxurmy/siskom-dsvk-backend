<?php
// app/Models/SyaratAdministrasiSeminar.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyaratAdministrasiSeminar extends Model
{
    use HasFactory;

    protected $table = 'syarat_administrasi_seminars';

    protected $fillable = [
        'seminar_id',
        'drive_folder_id',
        'proposal_url', 'proposal_drive_id', 'proposal_uploaded_at',
        'bukti_spp_url', 'bukti_spp_drive_id', 'bukti_spp_uploaded_at',
        'transkrip_url', 'transkrip_drive_id', 'transkrip_uploaded_at',
        'kartu_seminar_url', 'kartu_seminar_drive_id', 'kartu_seminar_uploaded_at', // <-- diganti
        'makalah_url', 'makalah_drive_id', 'makalah_uploaded_at',
        'status',
        'catatan_admin',
    ];
    protected $casts = [
        'proposal_uploaded_at'      => 'datetime',
        'bukti_spp_uploaded_at'     => 'datetime',
        'transkrip_uploaded_at'     => 'datetime',
        'kartu_seminar_uploaded_at' => 'datetime',
        'makalah_uploaded_at'       => 'datetime',
    ];

    public function seminar()
    {
        return $this->belongsTo(Seminar::class);
    }

    /**
     * Daftar 5 syarat + status lengkap/belum, dipakai buat ditampilkan di frontend.
     */
    public function daftarSyarat(): array
    {
        return [
            [
                'key'      => 'proposal',
                'label'    => 'Proposal yang sudah disetujui dosen pembimbing',
                'terisi'   => (bool) $this->proposal_url,
                'url'      => $this->proposal_url,
                'uploaded_at' => $this->proposal_uploaded_at,
            ],
            [
                'key'      => 'bukti_spp',
                'label'    => 'Foto copy bukti lunas SPP terbaru',
                'terisi'   => (bool) $this->bukti_spp_url,
                'url'      => $this->bukti_spp_url,
                'uploaded_at' => $this->bukti_spp_uploaded_at,
            ],
            [
                'key'      => 'transkrip',
                'label'    => 'Transkrip nilai (min. 110 SKS, IPK > 2.00, tanpa BL)',
                'terisi'   => (bool) $this->transkrip_url,
                'url'      => $this->transkrip_url,
                'uploaded_at' => $this->transkrip_uploaded_at,
            ],
            [
                'key'      => 'kartu_seminar',
                'label'    => 'Kartu seminar',
                'terisi'   => (bool) $this->kartu_seminar_url,
                'url'      => $this->kartu_seminar_url,
                'uploaded_at' => $this->kartu_seminar_uploaded_at,
            ],
            [
                'key'      => 'makalah',
                'label'    => 'Makalah seminar sesuai format',
                'terisi'   => (bool) $this->makalah_url,
                'url'      => $this->makalah_url,
                'uploaded_at' => $this->makalah_uploaded_at,
            ],
        ];
    }
}