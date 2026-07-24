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
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
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
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $pesertaKolokiums = PesertaKolokium::where('kolokium_id', $id)
            ->where('kolokium_id', $id)
            ->get();

        if ($pesertaKolokiums->isEmpty()) {
            return response()->json([
                'message' => 'Peserta kolokium tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Daftar peserta kolokium berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
    }

    public function myPesertaKolokium(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $pesertaKolokiums = PesertaKolokium::whereHas('kolokium', function ($query) use ($user) {
            $query->where('mahasiswa_id', $user->id);
        })->get();

        return response()->json([
            'message' => 'Daftar anggota forum kolokium berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
    }

    public function myKolokiumPeserta(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $kolokiums = Kolokium::whereHas('pesertaKolokium', function ($query) use ($user) {
            $query->where('mahasiswa_id', $user->id)
                ->where('status', 'hadir');
        })->latest()->get();

        return response()->json([
            'message' => 'Daftar kolokium yang diikuti berhasil didapatkan',
            'kolokiums' => $kolokiums,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'kolokium_id' => 'required|integer|exists:kolokiums,id',
        ]);

        $kolokium = Kolokium::find($validated['kolokium_id']);
        if (! $kolokium) {
            return response()->json([
                'message' => 'Kolokium tidak ditemukan',
            ], 404);
        }

        if ($kolokium->status !== 'approved') {
            return response()->json([
                'message' => 'Kolokium belum approved',
            ], 422);
        }

        if (! $kolokium->tanggal) {
            return response()->json([
                'message' => 'Tanggal kolokium belum tersedia',
            ], 422);
        }

        if (Carbon::today()->greaterThanOrEqualTo(Carbon::parse($kolokium->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Mahasiswa hanya dapat hadir sebelum hari H',
            ], 422);
        }

        if ((int) $kolokium->mahasiswa_id === (int) $user->id) {
            return response()->json([
                'message' => 'Mahasiswa pemilik kolokium tidak dapat menjadi peserta kolokium ini',
            ], 422);
        }

        $existingPeserta = PesertaKolokium::where('kolokium_id', $validated['kolokium_id'])
            ->where('mahasiswa_id', $user->id)
            ->first();

        if ($existingPeserta) {
            return response()->json([
                'message' => 'Peserta kolokium sudah ada',
            ], 409);
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

        $kolokium->update([
            'jumlahforum' => $jumlahForum,
        ]);

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
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:hadir,batal',
        ]);

        $peserta = PesertaKolokium::find($id);
        if (! $peserta) {
            return response()->json([
                'message' => 'Peserta kolokium tidak ditemukan',
            ], 404);
        }

        $kolokium = Kolokium::find($peserta->kolokium_id);
        if (! $kolokium) {
            return response()->json([
                'message' => 'Kolokium tidak ditemukan',
            ], 404);
        }

        if ($kolokium->status !== 'approved') {
            return response()->json([
                'message' => 'Kolokium belum approved',
            ], 422);
        }

        if (! $kolokium->tanggal) {
            return response()->json([
                'message' => 'Tanggal kolokium belum tersedia',
            ], 422);
        }

        if (Carbon::today()->greaterThanOrEqualTo(Carbon::parse($kolokium->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Perubahan status hanya dapat dilakukan sebelum hari H',
            ], 422);
        }

        $peserta->update([
            'status' => $validated['status'],
        ]);

        if ($validated['status'] === 'hadir') {
            $this->syncKartuKolokium($peserta);
        } else {
            KartuKolokium::where('peserta_kolokium_id', $peserta->id)->delete();
        }

        $jumlahForum = PesertaKolokium::where('kolokium_id', $peserta->kolokium_id)
            ->where('status', 'hadir')
            ->count();

        if ($kolokium) {
            $kolokium->update([
                'jumlahforum' => $jumlahForum,
            ]);
        }

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

    private function syncKartuKolokium(PesertaKolokium $peserta): void
    {
        $peserta->loadMissing(['kolokium.moderator', 'kolokium.mahasiswa', 'mahasiswa']);

        if (! $peserta->kolokium || ! $peserta->mahasiswa) {
            return;
        }

        KartuKolokium::updateOrCreate(
            [
                'peserta_kolokium_id' => $peserta->id,
            ],
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
