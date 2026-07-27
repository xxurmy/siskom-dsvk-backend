<?php

namespace App\Http\Controllers;

use App\Models\KartuSeminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KartuSeminarController extends Controller
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

        $query = KartuSeminar::query()
            ->whereHas('pesertaSeminar', function ($pesertaQuery) {
                $pesertaQuery->where('status', 'hadir');
            });

        if ($user->role === 'mahasiswa') {
            $query->where('forum_id', $user->id);
        } else {
            $query->where('moderator_id', $user->id);
        }

        // SEARCH: satu kata kunci dicocokkan ke beberapa kolom sekaligus.
        // Khusus dosen, ikut mencari di nama/nim forum (peserta yang hadir)
        // karena kolom itu hanya relevan & ditampilkan untuk role dosen.
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($searchQuery) use ($search, $user) {
                $searchQuery->where('namapemrasaran', 'like', "%{$search}%")
                    ->orWhere('nimpemrasaran', 'like', "%{$search}%")
                    ->orWhere('prodi', 'like', "%{$search}%")
                    ->orWhere('moderator', 'like', "%{$search}%");

                if ($user->role === 'dosen') {
                    $searchQuery->orWhere('namaforum', 'like', "%{$search}%")
                        ->orWhere('nimforum', 'like', "%{$search}%");
                }
            });
        }

        $kartuSeminars = $query->latest()
            ->paginate(10)
            ->through(fn (KartuSeminar $kartuSeminar) => $this->formatKartuSeminar($kartuSeminar, $user));

        return response()->json([
            'message' => 'Daftar kartu seminar berhasil didapatkan',
            'kartu_seminars' => $kartuSeminars,
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
            'statusparaf' => 'required|in:signed,absent',
        ]);

        $kartuSeminar = KartuSeminar::find($id);
        if (! $kartuSeminar) {
            return response()->json([
                'message' => 'Kartu seminar tidak ditemukan',
            ], 404);
        }

        if ((int) $kartuSeminar->moderator_id !== (int) $user->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if (! $kartuSeminar->tanggal) {
            return response()->json([
                'message' => 'Tanggal kartu seminar belum tersedia',
            ], 422);
        }

        if (Carbon::today()->lt(Carbon::parse($kartuSeminar->tanggal)->startOfDay())) {
            return response()->json([
                'message' => 'Dosen hanya dapat menandatangani kartu seminar pada hari H atau setelahnya',
            ], 422);
        }

        if ($kartuSeminar->statusparaf === 'signed') {
            return response()->json([
                'message' => 'Status paraf sudah signed',
                'kartu_seminar' => $this->formatKartuSeminar($kartuSeminar, $user),
            ]);
        }

        $updateData = [
            'statusparaf' => $validated['statusparaf'],
            'tandatangandosen' => null,
        ];

        if ($validated['statusparaf'] === 'signed') {
            if (empty($user->tandatangan)) {
                return response()->json([
                    'message' => 'Tanda tangan dosen belum tersedia',
                ], 422);
            }

            $updateData['tandatangandosen'] = $user->tandatangan;
        }

        $kartuSeminar->update($updateData);

        return response()->json([
            'message' => 'Status paraf kartu seminar berhasil diperbarui',
            'kartu_seminar' => $this->formatKartuSeminar($kartuSeminar, $user),
        ]);
    }

    private function formatKartuSeminar(KartuSeminar $kartuSeminar, User $user): array
    {
        $data = [
            'id' => $kartuSeminar->id,
            'seminar_id' => $kartuSeminar->seminar_id,
            'pemrasaran_id' => $kartuSeminar->pemrasaran_id,
            'moderator_id' => $kartuSeminar->moderator_id,
            'peserta_seminar_id' => $kartuSeminar->peserta_seminar_id,
            'forum_id' => $kartuSeminar->forum_id,
            'tanggal' => $kartuSeminar->tanggal,
            'waktu' => $kartuSeminar->waktu,
            'namapemrasaran' => $kartuSeminar->namapemrasaran,
            'nimpemrasaran' => $kartuSeminar->nimpemrasaran,
            'prodi' => $kartuSeminar->prodi,
            'moderator' => $kartuSeminar->moderator,
            'tandatangandosen' => $kartuSeminar->statusparaf === 'signed' ? $kartuSeminar->tandatangandosen : null,
            'statusparaf' => $kartuSeminar->statusparaf,
        ];

        if ($user->role === 'dosen') {
            $data['namaforum'] = $kartuSeminar->namaforum;
            $data['nimforum'] = $kartuSeminar->nimforum;
        }

        return $data;
    }
}
