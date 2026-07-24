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
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
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
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin' && $user->role !== 'dosen') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $pesertaSeminars = PesertaSeminar::where('seminar_id', $id)
            ->where('seminar_id', $id)
            ->get();

        if ($pesertaSeminars->isEmpty()) {
            return response()->json([
                'message' => 'Peserta seminar tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Daftar peserta seminar berhasil didapatkan',
            'peserta_seminars' => $pesertaSeminars,
        ]);
    }

    public function myPesertaSeminar(Request $request)
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

        $pesertaSeminars = PesertaSeminar::whereHas('seminar', function ($query) use ($user) {
            $query->where('mahasiswa_id', $user->id);
        })->get();

        return response()->json([
            'message' => 'Daftar anggota forum seminar berhasil didapatkan',
            'peserta_seminars' => $pesertaSeminars,
        ]);
    }

    public function mySeminarPeserta(Request $request)
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

        $seminars = Seminar::whereHas('pesertaSeminar', function ($query) use ($user) {
            $query->where('mahasiswa_id', $user->id)
                ->where('status', 'hadir');
        })->latest()->get();

        return response()->json([
            'message' => 'Daftar seminar yang diikuti berhasil didapatkan',
            'seminars' => $seminars,
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
            'seminar_id' => 'required|integer|exists:seminars,id',
        ]);

        $seminar = Seminar::find($validated['seminar_id']);
        if (! $seminar) {
            return response()->json([
                'message' => 'Seminar tidak ditemukan',
            ], 404);
        }

        if ($seminar->status !== 'approved') {
            return response()->json([
                'message' => 'Seminar belum approved',
            ], 422);
        }

        if (! $seminar->tanggal) {
            return response()->json([
                'message' => 'Tanggal seminar belum tersedia',
            ], 422);
        }

        if (Carbon::today()->greaterThanOrEqualTo(Carbon::parse($seminar->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Mahasiswa hanya dapat hadir sebelum hari H',
            ], 422);
        }

        if ((int) $seminar->mahasiswa_id === (int) $user->id) {
            return response()->json([
                'message' => 'Mahasiswa pemilik seminar tidak dapat menjadi peserta seminar ini',
            ], 422);
        }

        $existingPeserta = PesertaSeminar::where('seminar_id', $validated['seminar_id'])
            ->where('mahasiswa_id', $user->id)
            ->first();

        if ($existingPeserta) {
            return response()->json([
                'message' => 'Peserta seminar sudah ada',
            ], 409);
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

        $seminar->update([
            'jumlahforum' => $jumlahForum,
        ]);

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

        $peserta = PesertaSeminar::find($id);
        if (! $peserta) {
            return response()->json([
                'message' => 'Peserta seminar tidak ditemukan',
            ], 404);
        }

        $seminar = Seminar::find($peserta->seminar_id);
        if (! $seminar) {
            return response()->json([
                'message' => 'Seminar tidak ditemukan',
            ], 404);
        }

        if ($seminar->status !== 'approved') {
            return response()->json([
                'message' => 'Seminar belum approved',
            ], 422);
        }

        if (! $seminar->tanggal) {
            return response()->json([
                'message' => 'Tanggal seminar belum tersedia',
            ], 422);
        }

        if (Carbon::today()->greaterThanOrEqualTo(Carbon::parse($seminar->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Perubahan status hanya dapat dilakukan sebelum hari H',
            ], 422);
        }

        $peserta->update([
            'status' => $validated['status'],
        ]);

        if ($validated['status'] === 'hadir') {
            $this->syncKartuSeminar($peserta);
        } else {
            KartuSeminar::where('peserta_seminar_id', $peserta->id)->delete();
        }

        $jumlahForum = PesertaSeminar::where('seminar_id', $peserta->seminar_id)
            ->where('status', 'hadir')
            ->count();

        if ($seminar) {
            $seminar->update([
                'jumlahforum' => $jumlahForum,
            ]);
        }

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

    private function syncKartuSeminar(PesertaSeminar $peserta): void
    {
        $peserta->loadMissing(['seminar.moderator', 'seminar.mahasiswa', 'mahasiswa']);

        if (! $peserta->seminar || ! $peserta->mahasiswa) {
            return;
        }

        KartuSeminar::updateOrCreate(
            [
                'peserta_seminar_id' => $peserta->id,
            ],
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
