<?php
// app/Http/Controllers/AuthController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('username', $request->username)->first();
        
        if (! $user || ! Hash::check($request->password, $user->password)) 
            {
            return response()->json([
            'message' => 'Username atau password salah',
            ], 401);
        }

        if (! $user->status) {
            return response()->json([
                'message' => 'Akun tidak aktif, hubungi admin',
            ], 403);
        }

        // Hapus token lama (opsional, biar single-session)
        // $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil',
            'user'    => [
                'id'       => $user->id,
                'role'     => $user->role,
                'nama'     => $user->nama,
                'username' => $user->username,
                'email'    => $user->email,
                'prodi'    => $user->prodi,
                'foto'     => $user->foto,
            ],
            'token_type'   => 'Bearer',
            'access_token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout berhasil',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return response()->json([
                'message' => 'Password lama salah',
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'message' => 'Password berhasil diganti',
        ]);
    }

    /**
     * Kirim link reset password ke email user
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'Email tidak ditemukan',
            ], 404);
        }

        // Hapus token lama untuk email ini
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Generate token baru
        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email'      => $user->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        // Kirim email
        $user->notify(new ResetPasswordNotification($token));

        return response()->json([
            'message' => 'Link reset password telah dikirim ke email Anda',
        ]);
    }

    /**
     * Proses reset password menggunakan token dari email
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Token tidak valid atau sudah kedaluwarsa',
            ], 400);
        }

        // Cek token valid
        if (! Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Token tidak valid atau sudah kedaluwarsa',
            ], 400);
        }

        // Cek expired (60 menit)
        $createdAt = \Carbon\Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'message' => 'Token sudah kedaluwarsa, silakan minta link baru',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Hapus token setelah dipakai
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        // Opsional: revoke semua token akses lama demi keamanan
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset, silakan login dengan password baru',
        ]);
    }
}