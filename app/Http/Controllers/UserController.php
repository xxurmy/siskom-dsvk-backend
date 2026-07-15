<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
            'prodi'    => 'sometimes|required|string|max:100',
            'foto'     => 'nullable|string',
        ]);

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user'    => $user,
        ]);
    }


}