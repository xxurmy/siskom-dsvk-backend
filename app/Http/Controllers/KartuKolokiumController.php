<?php

namespace App\Http\Controllers;

use App\Models\KartuKolokium;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class KartuKolokiumController extends Controller
{
    /**
     * Jumlah data per halaman default & batas maksimal, konsisten dengan
     * KolokiumController supaya per_page tidak disalahgunakan (mis.
     * per_page=999999 yang bikin query berat).
     */
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE = 100;

    /**
     * Ambil & validasi nilai per_page dari query string.
     * Kalau tidak dikirim / tidak valid -> pakai DEFAULT_PER_PAGE.
     */
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

    /**
     * Daftar kartu kolokium milik user login (mahasiswa: sebagai pemrasaran
     * di forum; dosen: sebagai moderator), dengan search & pagination.
     *
     * Query params yang didukung:
     * - search      : cari bebas di nama/nim pemrasaran, prodi, & moderator
     *                 (untuk dosen, ikut mencari di nama/nim forum/peserta juga)
     * - page        : halaman ke berapa (Laravel paginator)
     * - per_page    : jumlah data per halaman (default 10, maksimal 100)
     */
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

        if ($user->role === 'dosen') {
            $query->orderBy('tanggal', 'desc');
        } else {
            $query->orderBy('tanggal', 'asc');
        }

        $perPage = $this->resolvePerPage($request);

        $kartuKolokiums = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (KartuKolokium $kartuKolokium) => $this->formatKartuKolokium($kartuKolokium, $user));

        return response()->json([
            'message' => 'Daftar kartu kolokium berhasil didapatkan',
            'kartu_kolokiums' => $kartuKolokiums,
        ]);
    }

    /**
     * Daftar kartu kolokium (peserta) untuk SATU kolokium tertentu — dipakai
     * halaman Absensi (dibuka dari tombol di baris Jadwal Kolokium).
     * Tidak dipaginate — halaman ini menampilkan semua peserta sekaligus.
     *
     * Diakses oleh:
     * - admin: boleh lihat kartu kolokium dari kolokium manapun.
     * - dosen: hanya boleh lihat kalau dia adalah moderator kolokium tsb
     *   (kartu kolokium yang moderator_id-nya bukan dia akan menghasilkan
     *   list kosong, bukan 403, supaya tidak membocorkan info kepemilikan).
     */
    public function byKolokium(Request $request, $kolokiumId)
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

        $query = KartuKolokium::query()
            ->where('kolokium_id', $kolokiumId)
            ->whereHas('pesertaKolokium', function ($pesertaQuery) {
                $pesertaQuery->where('status', 'hadir');
            });

        if ($user->role === 'dosen') {
            $query->where('moderator_id', $user->id);
        }

        $kartuKolokiums = $query->orderBy('namaforum')
            ->get()
            ->map(fn (KartuKolokium $kartuKolokium) => $this->formatKartuKolokium($kartuKolokium, $user))
            ->values();

        return response()->json([
            'message' => 'Daftar kartu kolokium berhasil didapatkan',
            'kartu_kolokiums' => $kartuKolokiums,
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
            'tanggal' => $kartuKolokium->tanggal?->format('Y-m-d'),
            'waktu' => $kartuKolokium->waktu,
            'namapemrasaran' => $kartuKolokium->namapemrasaran,
            'nimpemrasaran' => $kartuKolokium->nimpemrasaran,
            'prodi' => $kartuKolokium->prodi,
            'moderator' => $kartuKolokium->moderator,
        ];

        // Kolom peserta (nama/nim forum) relevan untuk dosen (moderator halaman
        // Kartu Kolokium miliknya) & admin (halaman Absensi per kolokium).
        if (in_array($user->role, ['dosen', 'admin'], true)) {
            $data['namaforum'] = $kartuKolokium->namaforum;
            $data['nimforum'] = $kartuKolokium->nimforum;
        }

        return $data;
    }
}