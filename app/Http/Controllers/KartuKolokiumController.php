<?php

namespace App\Http\Controllers;

use App\Models\KartuKolokium;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KartuKolokiumController extends Controller
{
    public function my(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if (! in_array($user->role, ['mahasiswa', 'dosen'], true)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = KartuKolokium::query()
            ->whereHas('pesertaKolokium', function ($pesertaQuery) {
                $pesertaQuery->where('status', 'hadir');
            });

        if ($user->role === 'mahasiswa') {
            $query->where('forum_id', $user->id);
        } else {
            $query->where('moderator_id', $user->id);
        }

        $kartuKolokiums = $query->latest()->get()->map(function ($kartuKolokium) use ($user) {
            return $this->formatKartuKolokium($kartuKolokium, $user);
        });

        return response()->json([
            'message' => 'Daftar kartu kolokium berhasil didapatkan',
            'kartu_kolokiums' => $kartuKolokiums,
        ]);
    }

    public function updateStatusParaf(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'dosen') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validated = $request->validate([
            'statusparaf' => 'required|in:signed',
        ]);

        $kartuKolokium = KartuKolokium::find($id);
        if (! $kartuKolokium) {
            return response()->json([
                'message' => 'Kartu kolokium tidak ditemukan',
            ], 404);
        }

        if ((int) $kartuKolokium->moderator_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $kartuKolokium->tanggal) {
            return response()->json([
                'message' => 'Tanggal kartu kolokium belum tersedia',
            ], 422);
        }

        if (Carbon::today()->lt(Carbon::parse($kartuKolokium->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Dosen hanya dapat menandatangani kartu kolokium pada hari H atau setelahnya',
            ], 422);
        }

        if (empty($user->tandatangan)) {
            return response()->json([
                'message' => 'Tanda tangan dosen belum tersedia',
            ], 422);
        }

        if ($kartuKolokium->statusparaf === 'signed') {
            return response()->json([
                'message' => 'Status paraf sudah signed',
                'kartu_kolokium' => $kartuKolokium,
            ]);
        }

        $kartuKolokium->update([
            'statusparaf' => $validated['statusparaf'],
            'tandatangandosen' => $user->tandatangan,
        ]);

        return response()->json([
            'message' => 'Status paraf kartu kolokium berhasil diperbarui',
            'kartu_kolokium' => $this->formatKartuKolokium($kartuKolokium, $user),
        ]);
    }

    private function formatKartuKolokium(KartuKolokium $kartuKolokium, User $user): array
    {
        $data = [
            'id' => $kartuKolokium->id,
            'kolokium_id' => $kartuKolokium->kolokium_id,
            'pemrasaran_id' => $kartuKolokium->pemrasaran_id,
            'moderator_id' => $kartuKolokium->moderator_id,
            'peserta_kolokium_id' => $kartuKolokium->peserta_kolokium_id,
            'forum_id' => $kartuKolokium->forum_id,
            'tanggal' => $kartuKolokium->tanggal,
            'waktu' => $kartuKolokium->waktu,
            'namapemrasaran' => $kartuKolokium->namapemrasaran,
            'nimpemrasaran' => $kartuKolokium->nimpemrasaran,
            'prodi' => $kartuKolokium->prodi,
            'moderator' => $kartuKolokium->moderator,
            'tandatangandosen' => $kartuKolokium->statusparaf === 'signed' ? $kartuKolokium->tandatangandosen : null,
            'statusparaf' => $kartuKolokium->statusparaf,
        ];

        if ($user->role === 'dosen') {
            $data['namaforum'] = $kartuKolokium->namaforum;
            $data['nimforum'] = $kartuKolokium->nimforum;
        }

        return $data;
    }
}
