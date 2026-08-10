<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\KartuKolokium;
use App\Models\KartuSeminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Jumlah data per halaman default & batas maksimal, dipakai oleh index()
     * supaya konsisten dan tidak disalahgunakan (mis. per_page=999999 yang
     * bikin query berat). Mengikuti pola yang sama seperti KolokiumController.
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

    public function profile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }
        return response()->json([
            'message' => 'Profil berhasil didapatkan', 
            'user' => $user
        ]);
    }

    /**
     * READ - admin only
     *
     * Query params yang didukung:
     * - role     : filter role ('admin' | 'dosen' | 'mahasiswa')
     * - prodi    : filter prodi
     * - status   : filter status
     * - search   : cari bebas di kolom nama, username, email, nim, & nip
     * - per_page : jumlah data per halaman (default 10, maksimal 100)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        if ($request->has('prodi')) {
            $query->where('prodi', $request->prodi);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // SEARCH: satu kata kunci dicocokkan ke beberapa kolom sekaligus
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('nama', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $perPage = $this->resolvePerPage($request);

        $users = $query->latest()->paginate($perPage)->withQueryString();

        $users->getCollection()->transform(function ($u) {
            return [
                'id'       => $u->id,
                'role'     => $u->role,
                'nama'     => $u->nama,
                'nim'      => $u->nim,
                'nip'      => $u->nip,
                'username' => $u->username,
                'email'    => $u->email,
                'prodi'    => $u->prodi,
                'foto'     => $u->foto,
                'tandatangan' => $u->tandatangan,
                'status'   => $u->status,
            ];
        });

        return response()->json([
            'message' => 'Daftar user berhasil didapatkan',
            'users'   => $users,
        ]);
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $user = User::find($id);
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'message' => 'Detail user berhasil didapatkan',
            'user'    => [
                'id'       => $user->id,
                'role'     => $user->role,
                'nama'     => $user->nama,
                'nim'      => $user->nim,
                'nip'      => $user->nip,
                'username' => $user->username,
                'email'    => $user->email,
                'prodi'    => $user->prodi,
                'foto'     => $user->foto,
                'tandatangan' => $user->tandatangan,
                'status'   => $user->status,
            ],
        ]);
    }

        /**
     * Daftar dosen — bisa diakses semua role yang sudah login (bukan admin-only)
     * Dipakai untuk dropdown "Dosen Pembimbing" di form daftar kolokium/seminar.
     */
    public function dosenList(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $dosens = User::where('role', 'dosen')
            ->select('id', 'nama', 'nip')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'message' => 'Daftar dosen berhasil didapatkan',
            'users' => $dosens,
        ]);
    }

    /**
     * Daftar mahasiswa (selain diri sendiri) — bisa diakses semua role yang login.
     * 
     */
    public function mahasiswaList(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $mahasiswas = User::where('role', 'mahasiswa')
            ->where('id', '!=', $user->id)
            ->select('id', 'nama', 'nim')
            ->orderBy('nama')
            ->get();

        return response()->json([
            'message' => 'Daftar mahasiswa berhasil didapatkan',
            'users' => $mahasiswas,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $validatedData = $request->validate([
            'nama'     => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,' . $user->id . '|max:50',
            'email'    => 'sometimes|required|email|unique:users,email,' . $user->id . '|max:255',
            'nim'      => 'sometimes|nullable|string|unique:users,nim,' . $user->id . '|max:11',
            'nip'      => 'sometimes|nullable|string|unique:users,nip,' . $user->id . '|max:20',
            'prodi'    => 'sometimes|required|string|max:100',
            'foto'     => 'nullable|string',
            'tandatangan' => 'nullable|string',
        ]);

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user'    => $user,
        ]);
    }

    public function uploadFotoProfil(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048' // 2MB
        ]);

        if ($user->foto) {
            Storage::disk('backblaze')->delete($this->extractPath($user->foto));
        }

        $path = $request->file('foto')->store('profile-photos', 'backblaze');

        $user->update([
            'foto' => url('/api/auth/images/' . $path),
        ]);

        return response()->json([
            'message' => 'Foto profil berhasil diupload',
            'foto' => $user->foto,
            'user' => $user,
        ]);
    }

    public function uploadTandaTangan(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'tandatangan' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($user->tandatangan) {
            Storage::disk('backblaze')->delete($this->extractPath($user->tandatangan));
        }

        $path = $request->file('tandatangan')->store('signatures', 'backblaze');

        $user->update([
            'tandatangan' => url('/api/auth/images/' . $path),
        ]);

        KartuKolokium::where('moderator_id', $user->id)
            ->where('statusparaf', 'signed')
            ->update([
            'tandatangandosen' => $user->tandatangan,
        ]);

        KartuSeminar::where('moderator_id', $user->id)
            ->where('statusparaf', 'signed')
            ->update([
                'tandatangandosen' => $user->tandatangan,
            ]);

        return response()->json([
            'message' => 'Tanda tangan berhasil diupload',
            'tandatangan' => $user->tandatangan,
            'user' => $user,
        ]);
    }

    /**
     * DELETE - hanya admin
     * Admin tidak bisa menghapus akunnya sendiri lewat endpoint ini.
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $targetUser = User::find($id);

        if (! $targetUser) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($targetUser->id === $user->id) {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun sendiri',
            ], 422);
        }

        if ($targetUser->role === 'admin') {
            return response()->json([
                'message' => 'Tidak dapat menghapus akun admin lain',
            ], 422);
        }

        // Bersihkan file terkait di Backblaze sebelum menghapus record
        if ($targetUser->foto) {
            Storage::disk('backblaze')->delete($this->extractPath($targetUser->foto));
        }
        if ($targetUser->tandatangan) {
            Storage::disk('backblaze')->delete($this->extractPath($targetUser->tandatangan));
        }

        $targetUser->delete();

        return response()->json([
            'message' => 'User berhasil dihapus',
        ]);
    }

    /**
     * Ambil kembali path relatif (mis. "profile-photos/xxx.jpg") dari URL lengkap
     * yang tersimpan di kolom foto/tandatangan, supaya bisa dipakai untuk delete di B2.
     */
    private function extractPath(string $url): string
    {
        $prefix = url('/api/auth/images/');
        return ltrim(str_replace($prefix, '', $url), '/');
    }

    /**
     * Proxy/stream file dari Backblaze B2 (private bucket) lewat Laravel,
     * dengan cache header 7 hari di browser/CDN.
     */
    public function showImage(Request $request, string $path)
    {
        $disk = Storage::disk('backblaze');

        if (! $disk->exists($path)) {
            return response()->json([
                'message' => 'File tidak ditemukan',
            ], 404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=604800', // 7 hari
        ]);
    }   
}