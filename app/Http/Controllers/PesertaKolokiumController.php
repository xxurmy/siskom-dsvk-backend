<?php

namespace App\Http\Controllers;

use App\Models\Kolokium;
use App\Models\PesertaKolokium;
use Illuminate\Http\Request;

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

        $pesertaKolokiums = PesertaKolokium::with(['kolokium', 'mahasiswa'])->get();

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

        $pesertaKolokiums = PesertaKolokium::with(['kolokium', 'mahasiswa'])
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

        $pesertaKolokiums = PesertaKolokium::with(['kolokium', 'mahasiswa'])
            ->whereHas('kolokium', function ($query) use ($user) {
                $query->where('mahasiswa_id', $user->id);
            })
            ->get();

        return response()->json([
            'message' => 'Daftar anggota forum kolokium berhasil didapatkan',
            'peserta_kolokiums' => $pesertaKolokiums,
        ]);
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

        $peserta->update([
            'status' => $validated['status'],
        ]);

        $jumlahForum = PesertaKolokium::where('kolokium_id', $peserta->kolokium_id)
            ->where('status', 'hadir')
            ->count();

        $kolokium = Kolokium::find($peserta->kolokium_id);
        if ($kolokium) {
            $kolokium->update([
                'jumlahforum' => $jumlahForum,
            ]);
        }

        return response()->json([
            'message' => 'Status peserta kolokium berhasil diperbarui',
            'peserta_kolokium' => $peserta,
            'jumlahforum' => $jumlahForum,
        ]);
    }
}
