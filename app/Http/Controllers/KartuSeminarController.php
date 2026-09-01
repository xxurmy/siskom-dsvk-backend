<?php

namespace App\Http\Controllers;

use App\Models\KartuSeminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class KartuSeminarController extends Controller
{
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE = 100;

    private function resolvePerPage(Request $request): int
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        if ($validator->fails()) {
            return self::DEFAULT_PER_PAGE;
        }

        return $validator->validated()['per_page'] ?? self::DEFAULT_PER_PAGE;
    }

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

        $perPage = $this->resolvePerPage($request);

        $kartuSeminars = $query
            ->orderBy('tanggal', 'desc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (KartuSeminar $kartuSeminar) => $this->formatKartuSeminar($kartuSeminar, $user));

        return response()->json([
            'message' => 'Daftar kartu seminar berhasil didapatkan',
            'kartu_seminars' => $kartuSeminars,
        ]);
    }

    /**
     * Daftar kartu seminar (peserta) untuk SATU seminar tertentu — dipakai
     * halaman Absensi (dibuka dari tombol di baris Jadwal Seminar).
     * Tidak dipaginate — halaman ini menampilkan semua peserta sekaligus.
     *
     * Diakses oleh:
     * - admin: boleh lihat kartu seminar dari seminar manapun.
     * - dosen: hanya boleh lihat kalau dia adalah moderator seminar tsb
     */
    public function bySeminar(Request $request, $seminarId)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if (! in_array($user->role, ['admin', 'dosen'], true)) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = KartuSeminar::query()
            ->where('seminar_id', $seminarId)
            ->whereHas('pesertaSeminar', function ($pesertaQuery) {
                $pesertaQuery->where('status', 'hadir');
            });

        if ($user->role === 'dosen') {
            $query->where('moderator_id', $user->id);
        }

        $kartuSeminars = $query->orderBy('namaforum')
            ->get()
            ->map(fn (KartuSeminar $kartuSeminar) => $this->formatKartuSeminar($kartuSeminar, $user))
            ->values();

        return response()->json([
            'message' => 'Daftar kartu seminar berhasil didapatkan',
            'kartu_seminars' => $kartuSeminars,
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
            'tanggal' => $kartuSeminar->tanggal?->format('Y-m-d'),
            'waktu' => $kartuSeminar->waktu,
            'namapemrasaran' => $kartuSeminar->namapemrasaran,
            'nimpemrasaran' => $kartuSeminar->nimpemrasaran,
            'prodi' => $kartuSeminar->prodi,
            'moderator' => $kartuSeminar->moderator,
        ];

        // Kolom peserta (nama/nim forum) relevan untuk dosen & admin
        if (in_array($user->role, ['dosen', 'admin'], true)) {
            $data['namaforum'] = $kartuSeminar->namaforum;
            $data['nimforum'] = $kartuSeminar->nimforum;
        }

        return $data;
    }
}