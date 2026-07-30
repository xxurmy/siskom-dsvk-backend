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
     * - statusparaf : filter status ('pending' | 'signed' | 'absent') — dipakai
     *                 mis. dashboard buat hitung "belum ditandatangani" secara
     *                 akurat lewat meta `total` paginator, tanpa perlu fetch
     *                 semua halaman ke client.
     * - hari_h      : kalau true (1), cuma ambil kartu yang tanggalnya sudah
     *                 hari ini atau sebelumnya (dosen baru bisa tanda tangan
     *                 kartu pada hari H atau setelahnya).
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

        // FILTER STATUS: dipakai dashboard buat hitung "belum ditandatangani"
        // secara akurat lewat meta `total` paginator, tanpa perlu fetch semua
        // halaman ke client.
        if ($request->filled('statusparaf')) {
            $query->where('statusparaf', $request->statusparaf);
        }

        // FILTER HARI H: kalau true, cuma ambil kartu yang tanggalnya SUDAH
        // hari ini atau sebelumnya. Dipakai dashboard buat "urgent count" —
        // karena dosen baru bisa tanda tangan kartu pada hari H atau setelahnya
        // (lihat pengecekan Carbon::today()->lt($tanggal) di updateStatusParaf()
        // di bawah), jadi kartu pending yang tanggalnya masih di masa depan
        // belum bisa ditindaklanjuti sama sekali dan tidak boleh dihitung urgent.
        if ($request->boolean('hari_h')) {
            $query->whereDate('tanggal', '<=', now()->toDateString());
        }

        $perPage = $this->resolvePerPage($request);

        $kartuKolokiums = $query->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (KartuKolokium $kartuKolokium) => $this->formatKartuKolokium($kartuKolokium, $user));

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
            'statusparaf' => 'required|in:signed,absent',
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

        if ($kartuKolokium->statusparaf === 'signed') {
            return response()->json([
                'message' => 'Status paraf sudah signed',
                'kartu_kolokium' => $kartuKolokium,
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

        $kartuKolokium->update($updateData);

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
            'tanggal' => $kartuKolokium->tanggal?->format('Y-m-d'),
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