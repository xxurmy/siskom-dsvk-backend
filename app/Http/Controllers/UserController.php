<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\KartuKolokium;
use App\Models\KartuSeminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
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

        $users = User::all()->map(function ($u) {
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
     * Dipakai untuk dropdown "Mahasiswa Pembahas" di form daftar kolokium/seminar.
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
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120' // 5MB
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