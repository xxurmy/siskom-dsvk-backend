<?php

namespace App\Http\Controllers;

use App\Models\KartuKolokium;
use App\Models\Kolokium;
use App\Models\PesertaKolokium;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesertaKolokiumController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaKolokiums = PesertaKolokium::get();

        return response()->json([
            'message' => 'Daftar peserta kolokium berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaKolokiums = PesertaKolokium::where('kolokium_id', $id)->get();

        if ($pesertaKolokiums->isEmpty()) {
            return response()->json(['message' => 'Peserta kolokium tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Daftar peserta kolokium berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
    }

    /**
     * SISI PEMBUAT KOLOKIUM (pemrasaran):
     * Daftar peserta dari kolokium-kolokium yang SAYA buat, dipakai untuk
     * melihat siapa saja yang sudah mendaftar hadir ke kolokium saya.
     * GET /auth/peserta-kolokium/my-peserta
     */
    public function myPesertaKolokium(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaKolokiums = PesertaKolokium::whereHas('kolokium', function ($query) use ($user) {
                $query->where('mahasiswa_id', $user->id);
            })
            ->with([
                'mahasiswa:id,nama,nim,prodi',
            ])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar peserta kolokium saya berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
    }

    /**
     * SISI PESERTA:
     * Daftar kolokium yang SAYA ikuti sebagai peserta, lengkap dengan
     * peserta_kolokium_id & status_kehadiran saya di masing-masing kolokium
     * (baik "hadir" maupun "batal") — dipakai halaman Jadwal Kolokium untuk
     * menentukan state tombol (Hadir / Hadir Ulang / badge Hadir).
     * GET /auth/peserta-kolokium/my-kolokium
     */
    public function myKolokiumPeserta(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaKolokiums = PesertaKolokium::where('mahasiswa_id', $user->id)
            ->with('kolokium')
            ->latest()
            ->get();

        $kolokiums = $pesertaKolokiums
            ->filter(fn (PesertaKolokium $peserta) => $peserta->kolokium !== null)
            ->map(function (PesertaKolokium $peserta) {
                $kolokium = $peserta->kolokium;

                return [
                    'id'                  => $kolokium->id,
                    'nama'                => $kolokium->nama,
                    'nim'                 => $kolokium->nim,
                    'prodi'               => $kolokium->prodi,
                    'judul'               => $kolokium->judul,
                    'tanggal'             => $kolokium->tanggal?->format('Y-m-d'),
                    'waktu'               => $kolokium->waktu,
                    'ruangan'             => $kolokium->ruangan,
                    'lokasi'              => $kolokium->lokasi,
                    'namadosenpembimbing' => $kolokium->namadosenpembimbing,
                    'namadosenmoderator'  => $kolokium->namadosenmoderator,
                    'jumlahforum'         => $kolokium->jumlahforum,
                    'peserta_kolokium_id' => $peserta->id,
                    'status_kehadiran'    => $peserta->status, // "hadir" | "batal"
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Daftar kolokium yang diikuti berhasil didapatkan',
            'kolokiums' => $kolokiums,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'kolokium_id' => 'required|integer|exists:kolokiums,id',
        ]);

        $kolokium = Kolokium::find($validated['kolokium_id']);
        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        if ($kolokium->status !== 'approved') {
            return response()->json(['message' => 'Kolokium belum approved'], 422);
        }

        if (! $kolokium->tanggal) {
            return response()->json(['message' => 'Tanggal kolokium belum tersedia'], 422);
        }

        $tanggalKolokium = Carbon::parse($kolokium->tanggal)->startOfDay();

        // Validasi: Maksimal H-1. Jika hari ini >= hari H, maka ditolak.
        if (Carbon::today()->greaterThanOrEqualTo($tanggalKolokium)) {
            return response()->json(['message' => 'Pendaftaran kehadiran maksimal dilakukan H-1 (hari pelaksanaan tidak bisa)'], 422);
        }

        // Blok validasi pendaftaran hanya dibuka mulai H-1 (lessThan) DIHAPUS di sini

        if ((int) $kolokium->mahasiswa_id === (int) $user->id) {
            return response()->json([
                'message' => 'Mahasiswa pemilik kolokium tidak dapat menjadi peserta kolokium ini',
            ], 422);
        }

        $existingPeserta = PesertaKolokium::where('kolokium_id', $validated['kolokium_id'])
            ->where('mahasiswa_id', $user->id)
            ->first();

        if ($existingPeserta) {
            return response()->json(['message' => 'Peserta kolokium sudah ada'], 409);
        }

        // Tidak boleh mendaftar hadir di 2 kolokium pada tanggal & waktu yang sama
        if ($this->hasJadwalBentrok($user->id, $kolokium)) {
            return response()->json([
                'message' => 'Anda tidak bisa mendaftar hadir di kolokium ini karena bentrok jadwal dengan kolokium lain yang sudah Anda hadiri',
            ], 422);
        }

        $peserta = PesertaKolokium::create([
            'kolokium_id' => $validated['kolokium_id'],
            'mahasiswa_id' => $user->id,
            'status' => 'hadir',
        ]);

        $this->syncKartuKolokium($peserta);

        $jumlahForum = PesertaKolokium::where('kolokium_id', $validated['kolokium_id'])
            ->where('status', 'hadir')
            ->count();

        $kolokium->update(['jumlahforum' => $jumlahForum]);

        return response()->json([
            'message' => 'Peserta kolokium berhasil dibuat',
            'peserta_kolokium' => [
                'id' => $peserta->id,
                'kolokium_id' => $peserta->kolokium_id,
                'mahasiswa_id' => $peserta->mahasiswa_id,
                'status' => $peserta->status,
                'created_at' => $peserta->created_at,
                'updated_at' => $peserta->updated_at,
            ],
            'jumlahforum' => $jumlahForum,
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:hadir,batal',
        ]);

        $peserta = PesertaKolokium::find($id);
        if (! $peserta) {
            return response()->json(['message' => 'Peserta kolokium tidak ditemukan'], 404);
        }

        // Pastikan record ini benar-benar milik mahasiswa yang sedang login
        if ((int) $peserta->mahasiswa_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $kolokium = Kolokium::find($peserta->kolokium_id);
        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        if ($kolokium->status !== 'approved') {
            return response()->json(['message' => 'Kolokium belum approved'], 422);
        }

        if (! $kolokium->tanggal) {
            return response()->json(['message' => 'Tanggal kolokium belum tersedia'], 422);
        }

        $tanggalKolokium = Carbon::parse($kolokium->tanggal)->startOfDay();

        // Validasi: Maksimal H-1. Jika hari ini >= hari H, maka ditolak.
        if (Carbon::today()->greaterThanOrEqualTo($tanggalKolokium)) {
            return response()->json(['message' => 'Perubahan status hanya dapat dilakukan maksimal H-1 sebelum hari H'], 422);
        }

        if ($validated['status'] === 'hadir') {
            // Blok pengecekan H-1 (lessThan) DIHAPUS di sini agar bisa daftar kapan saja sebelum hari H

            // Cek jadwal bentrok
            if ($this->hasJadwalBentrok($user->id, $kolokium, $peserta->id)) {
                return response()->json([
                    'message' => 'Anda tidak bisa hadir ulang di kolokium ini karena bentrok jadwal dengan kolokium lain yang sudah Anda hadiri',
                ], 422);
            }
        }

        $peserta->update(['status' => $validated['status']]);

        if ($validated['status'] === 'hadir') {
            $this->syncKartuKolokium($peserta);
        } else {
            KartuKolokium::where('peserta_kolokium_id', $peserta->id)->delete();
        }

        $jumlahForum = PesertaKolokium::where('kolokium_id', $peserta->kolokium_id)
            ->where('status', 'hadir')
            ->count();

        $kolokium->update(['jumlahforum' => $jumlahForum]);

        return response()->json([
            'message' => 'Status peserta kolokium berhasil diperbarui',
            'peserta_kolokium' => [
                'id' => $peserta->id,
                'kolokium_id' => $peserta->kolokium_id,
                'mahasiswa_id' => $peserta->mahasiswa_id,
                'status' => $peserta->status,
                'created_at' => $peserta->created_at,
                'updated_at' => $peserta->updated_at,
            ],
            'jumlahforum' => $jumlahForum,
        ]);
    }

    /**
     * Cek apakah user sudah "hadir" di kolokium LAIN dengan tanggal & waktu
     * yang sama dengan $kolokium (dipakai untuk mencegah bentrok jadwal).
     */
    private function hasJadwalBentrok(int $mahasiswaId, Kolokium $kolokium, ?int $excludePesertaId = null): bool
    {
        return PesertaKolokium::where('mahasiswa_id', $mahasiswaId)
            ->where('status', 'hadir')
            ->when($excludePesertaId, fn ($query) => $query->where('id', '!=', $excludePesertaId))
            ->whereHas('kolokium', function ($query) use ($kolokium) {
                $query->where('id', '!=', $kolokium->id)
                    ->where('tanggal', $kolokium->tanggal)
                    ->where('waktu', $kolokium->waktu);
            })
            ->exists();
    }

    private function syncKartuKolokium(PesertaKolokium $peserta): void
    {
        $peserta->loadMissing(['kolokium.moderator', 'kolokium.mahasiswa', 'mahasiswa']);

        if (! $peserta->kolokium || ! $peserta->mahasiswa) {
            return;
        }

        KartuKolokium::updateOrCreate(
            ['peserta_kolokium_id' => $peserta->id],
            [
                'kolokium_id' => $peserta->kolokium_id,
                'pemrasaran_id' => $peserta->kolokium->mahasiswa_id,
                'moderator_id' => $peserta->kolokium->moderator_id,
                'forum_id' => $peserta->mahasiswa_id,
                'tanggal' => $peserta->kolokium->tanggal,
                'waktu' => $peserta->kolokium->waktu,
                'namapemrasaran' => $peserta->kolokium->mahasiswa?->nama,
                'nimpemrasaran' => $peserta->kolokium->mahasiswa?->nim,
                'prodi' => $peserta->kolokium->mahasiswa?->prodi,
                'moderator' => $peserta->kolokium->moderator?->nama,
                'namaforum' => $peserta->mahasiswa?->nama,
                'nimforum' => $peserta->mahasiswa?->nim,
                'tandatangandosen' => null,
                'statusparaf' => 'pending',
            ]
        );
    }
}