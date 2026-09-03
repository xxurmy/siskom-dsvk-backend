<?php
// app/Http/Controllers/SyaratAdministrasiKolokiumController.php

namespace App\Http\Controllers;

use App\Models\Kolokium;
use App\Models\SyaratAdministrasiKolokium;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SyaratAdministrasiKolokiumController extends Controller
{
    /**
     * Mapping key syarat -> [kolom url, kolom uploaded_at, aturan validasi]
     * PENTING: key di sini harus PERSIS SAMA dengan key yang dikembalikan
     * SyaratAdministrasiKolokium::daftarSyarat() di model, supaya frontend
     * (GET utk nampilin status & POST utk upload) mengacu ke key yang sama.
     */
    private const SYARAT_MAP = [
        'proposal' => [
            'url_col' => 'proposal_url', 'at_col' => 'proposal_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:51200', // 50MB
            'label' => 'Proposal',
        ],
        'bukti_spp' => [
            'url_col' => 'bukti_spp_url', 'at_col' => 'bukti_spp_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:10240', // 10MB
            'label' => 'Bukti Lunas SPP',
        ],
        'transkrip' => [
            'url_col' => 'transkrip_url', 'at_col' => 'transkrip_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:10240', // 10MB
            'label' => 'Transkrip Nilai',
        ],
        'kartu_kolokium' => [
            'url_col' => 'kartu_kolokium_url', 'at_col' => 'kartu_kolokium_uploaded_at',
            'rule' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240', // 10MB
            'label' => 'Kartu Kolokium',
        ],
        'makalah' => [
            'url_col' => 'makalah_url', 'at_col' => 'makalah_uploaded_at',
            'rule' => 'required|file|mimes:pdf|max:51200', // 50MB
            'label' => 'Makalah Kolokium',
        ],
    ];

    /**
     * Jenis folder root untuk modul ini. Dipakai supaya berkas kolokium &
     * seminar tidak pernah tercampur dalam struktur folder di storage lokal:
     * storage/app/private/berkas-{JENIS_FOLDER}/{nim}_{nama}/...
     */
    private const JENIS_FOLDER = 'kolokium';

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
     *
     * Disimpan ke disk 'private' (storage/app/private, TIDAK bisa diakses
     * langsung lewat URL publik) di folder:
     * berkas-kolokium/{nim}_{nama}/{nama-file-tetap}
     *
     * Karena nama file SELALU SAMA per syarat (mis. proposal_{nim}.pdf),
     * upload ulang otomatis menimpa file lama -> anti-duplikat tanpa
     * logic hapus-lalu-buat-baru.
     *
     * PERBAIKAN: untuk syarat yang mengizinkan lebih dari satu ekstensi
     * (kartu_kolokium: pdf/jpg/jpeg/png), nama file lama bisa beda ekstensi
     * dari file baru (mis. dulu .jpg, sekarang upload .pdf). Kalau hanya
     * mengandalkan overwrite by-name, file lama dengan ekstensi berbeda
     * akan jadi sampah (orphan) di storage. Makanya sebelum simpan file
     * baru, file lama (jika ada & path-nya berbeda) dihapus eksplisit.
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

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();

        // Struktur folder: storage/app/private/berkas-kolokium/{nim}_{nama}/
        $folderName = $this->sanitizeFolderName($kolokium->nim . '_' . $kolokium->nama);
        $folderPath = 'berkas-' . self::JENIS_FOLDER . '/' . $folderName;

        // Nama file tetap per syarat -> upload ulang otomatis replace file lama
        $fileName = Str::slug($config['label']) . '_' . $kolokium->nim . '.' . $extension;
        $newPath = $folderPath . '/' . $fileName;

        // Hapus file lama kalau path-nya berbeda dari file baru (mis. beda
        // ekstensi: jpg -> pdf), supaya tidak ada file orphan menumpuk.
        $oldPath = $syarat->{$config['url_col']};
        if ($oldPath && $oldPath !== $newPath && Storage::disk('private')->exists($oldPath)) {
            Storage::disk('private')->delete($oldPath);
        }

        $path = $file->storeAs($folderPath, $fileName, 'private');

        // URL disimpan sebagai path relatif saja (bukan URL publik), karena
        // file harus diambil lewat route ber-auth (SyaratAdministrasiKolokiumController@showFile),
        // bukan diakses langsung. Frontend membangun URL akses dari path ini.
        $syarat->update([
            $config['url_col'] => $path,
            $config['at_col']  => now(),
            'status' => 'menunggu_verifikasi',
        ]);

        return response()->json([
            'message' => $config['label'] . ' berhasil diupload',
            'url'     => $path,
            'syarat'  => $syarat->fresh()->daftarSyarat(),
        ]);
    }

    /**
     * GET - stream/download file syarat administrasi secara aman (butuh login).
     * Mahasiswa hanya bisa akses berkas miliknya sendiri, admin bisa akses semua.
     * Route: GET /kolokium/{id}/syarat-administrasi/{syaratKey}/file
     *
     * PERBAIKAN: sebelumnya pakai Cache-Control: max-age=3600 tanpa validator,
     * sehingga browser menyajikan file dari cache lokal selama 1 jam meski
     * file di server sudah di-overwrite (record uploaded_at sudah berubah,
     * file fisik sudah baru). Sekarang pakai no-cache + Last-Modified/ETag
     * berbasis kolom uploaded_at, jadi browser WAJIB revalidate ke server
     * tiap request: kalau file belum berubah dapat 304 (hemat bandwidth),
     * begitu file berubah langsung dapat isi terbaru.
     */
    public function showFile($kolokiumId, string $syaratKey, Request $request)
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

        if ($user->role === 'mahasiswa') {
            if ($kolokium->mahasiswa_id !== $user->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $syarat = SyaratAdministrasiKolokium::where('kolokium_id', $kolokium->id)->first();
        $config = self::SYARAT_MAP[$syaratKey];
        $path = $syarat?->{$config['url_col']};

        if (! $path || ! Storage::disk('private')->exists($path)) {
            return response()->json(['message' => 'Berkas tidak ditemukan'], 404);
        }

        $lastModified = $syarat->{$config['at_col']};

        return Storage::disk('private')->response($path, null, [
            'Cache-Control' => 'private, no-cache, must-revalidate',
            'Last-Modified' => $lastModified?->toRfc7231String(),
            'ETag' => '"' . md5($path . $lastModified) . '"',
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

    private function sanitizeFolderName(string $name): string
    {
        return preg_replace('/[\/\\\?%*:|"<>]/', '-', $name);
    }
}