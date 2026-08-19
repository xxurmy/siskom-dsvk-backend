<?php
// app/Http/Controllers/SyaratAdministrasiKolokiumController.php

namespace App\Http\Controllers;

use App\Models\Kolokium;
use App\Models\SyaratAdministrasiKolokium;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SyaratAdministrasiKolokiumController extends Controller
{
    /**
     * Mapping key syarat -> [kolom url, kolom drive_id, kolom uploaded_at, aturan validasi]
     * PENTING: key di sini harus PERSIS SAMA dengan key yang dikembalikan
     * SyaratAdministrasiKolokium::daftarSyarat() di model, supaya frontend
     * (GET utk nampilin status & POST utk upload) mengacu ke key yang sama.
     */
    private const SYARAT_MAP = [
        'proposal' => [
            'url_col' => 'proposal_url', 'id_col' => 'proposal_drive_id', 'at_col' => 'proposal_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:51200', // 50MB
            'label' => 'Proposal',
        ],
        'bukti_spp' => [
            'url_col' => 'bukti_spp_url', 'id_col' => 'bukti_spp_drive_id', 'at_col' => 'bukti_spp_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:10240', // 10MB
            'label' => 'Bukti Lunas SPP',
        ],
        'transkrip' => [
            'url_col' => 'transkrip_url', 'id_col' => 'transkrip_drive_id', 'at_col' => 'transkrip_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:10240', // 10MB
            'label' => 'Transkrip Nilai',
        ],
        'kartu_kolokium' => [
            'url_col' => 'kartu_kolokium_url', 'id_col' => 'kartu_kolokium_drive_id', 'at_col' => 'kartu_kolokium_uploaded_at',
            'rule' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
            'label' => 'Kartu Kolokium',
        ],
        'makalah' => [
            'url_col' => 'makalah_url', 'id_col' => 'makalah_drive_id', 'at_col' => 'makalah_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:51200', // 50MB
            'label' => 'Makalah Kolokium',
        ],
    ];

    /**
     * Jenis folder root di Google Drive untuk modul ini. Dikirim eksplisit
     * ke GoogleDriveService::findOrCreateUserFolder() supaya berkas
     * kolokium & seminar tidak pernah tercampur, dan supaya kedua
     * controller (Kolokium & Seminar) konsisten memberi argumen ini
     * secara eksplisit, bukan mengandalkan nilai default di service.
     */
    private const JENIS_FOLDER = 'kolokium';

    private GoogleDriveService $driveService;

    public function __construct(GoogleDriveService $driveService)
    {
        $this->driveService = $driveService;
    }

    /**
     * GET - ambil status syarat administrasi untuk 1 kolokium.
     * Bisa diakses mahasiswa pemilik kolokium tsb & admin.
     */
    public function show($kolokiumId, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $kolokium = Kolokium::find($kolokiumId);
        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        if ($user->role === 'mahasiswa') {
            if ($kolokium->mahasiswa_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $syarat = SyaratAdministrasiKolokium::firstOrCreate(['kolokium_id' => $kolokium->id]);

        return response()->json([
            'message' => 'Syarat administrasi berhasil didapatkan',
            'status'  => $syarat->status,
            'catatan_admin' => $syarat->catatan_admin,
            'syarat'  => $syarat->daftarSyarat(),
        ]);
    }

    /**
     * POST - upload salah satu file syarat administrasi.
     * $syaratKey: proposal | bukti_spp | transkrip | kartu_kolokium | makalah
     * Hanya mahasiswa pemilik kolokium yang boleh upload.
     */
    public function upload($kolokiumId, string $syaratKey, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if (! array_key_exists($syaratKey, self::SYARAT_MAP)) {
            return response()->json(['message' => 'Jenis syarat tidak valid'], 422);
        }

        $kolokium = Kolokium::find($kolokiumId);
        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa' || $kolokium->mahasiswa_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $config = self::SYARAT_MAP[$syaratKey];

        $validator = Validator::make($request->all(), [
            'file' => $config['rule'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $syarat = SyaratAdministrasiKolokium::firstOrCreate(['kolokium_id' => $kolokium->id]);

        // Buat/reuse folder gdrive khusus mahasiswa ini.
        // Struktur folder: berkas-siskom-dsvk (root) -> kolokium -> nim_nama
        if (! $syarat->drive_folder_id) {
            $folderId = $this->driveService->findOrCreateUserFolder(
                $kolokium->nim,
                $kolokium->nama,
                self::JENIS_FOLDER
            );
            $syarat->drive_folder_id = $folderId;
        }

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $fileName = Str::slug($config['label']) . '_' . $kolokium->nim . '.' . $extension;

        // ANTI-DUPLIKAT: replace file yang sama kalau sudah pernah upload sebelumnya,
        // bukan bikin file baru. ID & link Google Drive tetap sama.
        $oldDriveId = $syarat->{$config['id_col']};
        $uploaded = null;

        if ($oldDriveId) {
            $uploaded = $this->driveService->replaceFile($oldDriveId, $file, $fileName);
        }

        // Belum pernah upload sebelumnya, ATAU file lama sudah tidak ada di Drive (fallback)
        if (! $uploaded) {
            $uploaded = $this->driveService->uploadFile($syarat->drive_folder_id, $file, $fileName);
        }

        $syarat->update([
            $config['url_col'] => $uploaded['url'],
            $config['id_col']  => $uploaded['id'],
            $config['at_col']  => now(),
            'status' => 'menunggu_verifikasi',
        ]);

        return response()->json([
            'message' => $config['label'] . ' berhasil diupload',
            'url'     => $uploaded['url'],
            'syarat'  => $syarat->fresh()->daftarSyarat(),
        ]);
    }

    /**
     * PATCH - admin verifikasi kelengkapan syarat administrasi.
     */
    public function verify($kolokiumId, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status'        => 'required|in:lengkap,ditolak,menunggu_verifikasi',
            'catatan_admin' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf($request->input('status') === 'ditolak'),
            ],
        ], [
            'catatan_admin.required' => 'Catatan admin wajib diisi ketika status ditolak.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $syarat = SyaratAdministrasiKolokium::where('kolokium_id', $kolokiumId)->first();
        if (! $syarat) {
            return response()->json(['message' => 'Syarat administrasi belum diisi'], 404);
        }

        $syarat->update($validator->validated());

        return response()->json([
            'message' => 'Status syarat administrasi berhasil diperbarui',
            'syarat'  => $syarat->fresh(),
        ]);
    }
}