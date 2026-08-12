<?php
// app/Http/Controllers/RegisterController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /**
     * Register Dosen
     */
    public function registerDosen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'     => 'required|string|max:255',
            'nip'      => 'required|string|unique:users,nip|max:18',
            'username' => 'required|string|unique:users,username|max:50',
            'prodi'    => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed', // butuh password_confirmation
            'foto'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $dosen = User::create([
            'role'     => 'dosen',
            'nama'     => $request->nama,
            'nip'      => $request->nip,
            'username' => $request->username,
            'prodi'    => $request->prodi,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'foto'     => $request->foto,
            'status'   => true,
        ]);

        $token = $dosen->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registrasi dosen berhasil',
            'user'         => $dosen,
            'token_type'   => 'Bearer',
            'access_token' => $token,
        ], 201);
    }

    /**
     * Register Mahasiswa
     */
    public function registerMahasiswa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'     => 'required|string|max:255',
            'nim'      => 'required|string|unique:users,nim|max:11',
            'username' => 'required|string|unique:users,username|max:50',
            'prodi'    => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email|max:255',
            'password' => 'required|string|min:8|confirmed',
            'foto'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $mahasiswa = User::create([
            'role'     => 'mahasiswa',
            'nama'     => $request->nama,
            'nim'      => $request->nim,
            'username' => $request->username,
            'prodi'    => $request->prodi,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'foto'     => $request->foto,
            'status'   => true,
        ]);

        $token = $mahasiswa->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Registrasi mahasiswa berhasil',
            'user'         => $mahasiswa,
            'token_type'   => 'Bearer',
            'access_token' => $token,
        ], 201);
    }
}