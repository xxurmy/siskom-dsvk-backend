<?php

namespace App\Http\Controllers;

use App\Models\KartuSeminar;
use App\Models\PesertaSeminar;
use App\Models\Seminar;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PesertaSeminarController extends Controller
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

        $pesertaSeminars = PesertaSeminar::get();

        return response()->json([
            'message' => 'Daftar peserta seminar berhasil didapatkan',
            'peserta_seminars' => $pesertaSeminars,
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

        $pesertaSeminars = PesertaSeminar::where('seminar_id', $id)->get();

        if ($pesertaSeminars->isEmpty()) {
            return response()->json(['message' => 'Peserta seminar tidak ditemukan'], 404);
        }

        return response()->json([
            'message' => 'Daftar peserta seminar berhasil didapatkan',
            'peserta_seminars' => $pesertaSeminars,
        ]);
    }

    /**
     * SISI PEMBUAT SEMINAR (pemrasaran):
     * Daftar peserta dari seminar-seminar yang SAYA buat, dipakai untuk
     * melihat siapa saja yang sudah mendaftar hadir ke seminar saya.
     * GET /auth/peserta-seminar/my-peserta
     */
    public function myPesertaSeminar(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaSeminars = PesertaSeminar::whereHas('seminar', function ($query) use ($user) {
                $query->where('mahasiswa_id', $user->id);
            })
            ->with([
                'mahasiswa:id,nama,nim,prodi',
            ])
            ->latest()
            ->get();

        return response()->json([
            'message' => 'Daftar peserta seminar saya berhasil didapatkan',
            'peserta_seminars' => $pesertaSeminars,
        ]);
    }

    /**
     * SISI PESERTA:
     * Daftar seminar yang SAYA ikuti sebagai peserta, lengkap dengan
     * peserta_seminar_id & status_kehadiran saya di masing-masing seminar
     * (baik "hadir" maupun "batal") — dipakai halaman Jadwal Seminar untuk
     * menentukan state tombol (Hadir / Hadir Ulang / badge Hadir).
     * GET /auth/peserta-seminar/my-seminar
     */
    public function mySeminarPeserta(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $pesertaSeminars = PesertaSeminar::where('mahasiswa_id', $user->id)
            ->with('seminar')
            ->latest()
            ->get();

        $seminars = $pesertaSeminars
            ->filter(fn (PesertaSeminar $peserta) => $peserta->seminar !== null)
            ->map(function (PesertaSeminar $peserta) {
                $seminar = $peserta->seminar;

                return [
                    'id'                  => $seminar->id,
                    'nama'                => $seminar->nama,
                    'nim'                 => $seminar->nim,
                    'prodi'               => $seminar->prodi,
                    'judul'               => $seminar->judul,
                    'tanggal'             => $seminar->tanggal?->format('Y-m-d'),
                    'waktu'               => $seminar->waktu,
                    'ruangan'             => $seminar->ruangan,
                    'lokasi'              => $seminar->lokasi,
                    'namadosenpembimbing' => $seminar->namadosenpembimbing,
                    'namadosenmoderator'  => $seminar->namadosenmoderator,
                    'jumlahforum'         => $seminar->jumlahforum,
                    'peserta_seminar_id'  => $peserta->id,
                    'status_kehadiran'    => $peserta->status, // "hadir" | "batal"
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Daftar seminar yang diikuti berhasil didapatkan',
            'seminars' => $seminars,
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
            'seminar_id' => 'required|integer|exists:seminars,id',
        ]);

        $seminar = Seminar::find($validated['seminar_id']);
        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        if ($seminar->status !== 'approved') {
            return response()->json(['message' => 'Seminar belum approved'], 422);
        }

        if (! $seminar->tanggal) {
            return response()->json(['message' => 'Tanggal seminar belum tersedia'], 422);
        }

        $tanggalSeminar = Carbon::parse($seminar->tanggal)->startOfDay();

        // Validasi: Maksimal H-1. Jika hari ini >= hari H, maka ditolak.
        if (Carbon::today()->greaterThanOrEqualTo($tanggalSeminar)) {
            return response()->json(['message' => 'Pendaftaran kehadiran maksimal dilakukan H-1 (hari pelaksanaan tidak bisa)'], 422);
        }

        // Blok validasi pendaftaran hanya dibuka mulai H-1 (lessThan) DIHAPUS di sini

        if ((int) $seminar->mahasiswa_id === (int) $user->id) {
            return response()->json([
                'message' => 'Mahasiswa pemilik seminar tidak dapat menjadi peserta seminar ini',
            ], 422);
        }

        $existingPeserta = PesertaSeminar::where('seminar_id', $validated['seminar_id'])
            ->where('mahasiswa_id', $user->id)
            ->first();

        if ($existingPeserta) {
            return response()->json(['message' => 'Peserta seminar sudah ada'], 409);
        }

        // Tidak boleh mendaftar hadir di 2 seminar pada tanggal & waktu yang sama
        if ($this->hasJadwalBentrok($user->id, $seminar)) {
            return response()->json([
                'message' => 'Anda tidak bisa mendaftar hadir di seminar ini karena bentrok jadwal dengan seminar lain yang sudah Anda hadiri',
            ], 422);
        }

        $peserta = PesertaSeminar::create([
            'seminar_id' => $validated['seminar_id'],
            'mahasiswa_id' => $user->id,
            'status' => 'hadir',
        ]);

        $this->syncKartuSeminar($peserta);

        $jumlahForum = PesertaSeminar::where('seminar_id', $validated['seminar_id'])
            ->where('status', 'hadir')
            ->count();

        $seminar->update(['jumlahforum' => $jumlahForum]);

        return response()->json([
            'message' => 'Peserta seminar berhasil dibuat',
            'peserta_seminar' => [
                'id' => $peserta->id,
                'seminar_id' => $peserta->seminar_id,
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

        $peserta = PesertaSeminar::find($id);
        if (! $peserta) {
            return response()->json(['message' => 'Peserta seminar tidak ditemukan'], 404);
        }

        // Pastikan record ini benar-benar milik mahasiswa yang sedang login
        if ((int) $peserta->mahasiswa_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $seminar = Seminar::find($peserta->seminar_id);
        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        if ($seminar->status !== 'approved') {
            return response()->json(['message' => 'Seminar belum approved'], 422);
        }

        if (! $seminar->tanggal) {
            return response()->json(['message' => 'Tanggal seminar belum tersedia'], 422);
        }

        $tanggalSeminar = Carbon::parse($seminar->tanggal)->startOfDay();

        // Validasi: Maksimal H-1. Jika hari ini >= hari H, maka ditolak.
        if (Carbon::today()->greaterThanOrEqualTo($tanggalSeminar)) {
            return response()->json(['message' => 'Perubahan status hanya dapat dilakukan maksimal H-1 sebelum hari H'], 422);
        }

        if ($validated['status'] === 'hadir') {
            // Blok pengecekan H-1 (lessThan) DIHAPUS di sini agar bisa daftar kapan saja sebelum hari H

            // Cek jadwal bentrok
            if ($this->hasJadwalBentrok($user->id, $seminar, $peserta->id)) {
                return response()->json([
                    'message' => 'Anda tidak bisa hadir ulang di seminar ini karena bentrok jadwal dengan seminar lain yang sudah Anda hadiri',
                ], 422);
            }
        }

        $peserta->update(['status' => $validated['status']]);

        if ($validated['status'] === 'hadir') {
            $this->syncKartuSeminar($peserta);
        } else {
            KartuSeminar::where('peserta_seminar_id', $peserta->id)->delete();
        }

        $jumlahForum = PesertaSeminar::where('seminar_id', $peserta->seminar_id)
            ->where('status', 'hadir')
            ->count();

        $seminar->update(['jumlahforum' => $jumlahForum]);

        return response()->json([
            'message' => 'Status peserta seminar berhasil diperbarui',
            'peserta_seminar' => [
                'id' => $peserta->id,
                'seminar_id' => $peserta->seminar_id,
                'mahasiswa_id' => $peserta->mahasiswa_id,
                'status' => $peserta->status,
                'created_at' => $peserta->created_at,
                'updated_at' => $peserta->updated_at,
            ],
            'jumlahforum' => $jumlahForum,
        ]);
    }

    /**
     * Cek apakah user sudah "hadir" di seminar LAIN dengan tanggal & waktu
     * yang sama dengan $seminar (dipakai untuk mencegah bentrok jadwal).
     */
    private function hasJadwalBentrok(int $mahasiswaId, Seminar $seminar, ?int $excludePesertaId = null): bool
    {
        return PesertaSeminar::where('mahasiswa_id', $mahasiswaId)
            ->where('status', 'hadir')
            ->when($excludePesertaId, fn ($query) => $query->where('id', '!=', $excludePesertaId))
            ->whereHas('seminar', function ($query) use ($seminar) {
                $query->where('id', '!=', $seminar->id)
                    ->where('tanggal', $seminar->tanggal)
                    ->where('waktu', $seminar->waktu);
            })
            ->exists();
    }

    private function syncKartuSeminar(PesertaSeminar $peserta): void
    {
        $peserta->loadMissing(['seminar.moderator', 'seminar.mahasiswa', 'mahasiswa']);

        if (! $peserta->seminar || ! $peserta->mahasiswa) {
            return;
        }

        KartuSeminar::updateOrCreate(
            ['peserta_seminar_id' => $peserta->id],
            [
                'seminar_id' => $peserta->seminar_id,
                'pemrasaran_id' => $peserta->seminar->mahasiswa_id,
                'moderator_id' => $peserta->seminar->moderator_id,
                'forum_id' => $peserta->mahasiswa_id,
                'tanggal' => $peserta->seminar->tanggal,
                'waktu' => $peserta->seminar->waktu,
                'namapemrasaran' => $peserta->seminar->mahasiswa?->nama,
                'nimpemrasaran' => $peserta->seminar->mahasiswa?->nim,
                'prodi' => $peserta->seminar->mahasiswa?->prodi,
                'moderator' => $peserta->seminar->moderator?->nama,
                'namaforum' => $peserta->mahasiswa?->nama,
                'nimforum' => $peserta->mahasiswa?->nim,
                'tandatangandosen' => null,
                'statusparaf' => 'pending',
            ]
        );
    }
}