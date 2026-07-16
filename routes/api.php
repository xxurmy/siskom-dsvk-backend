<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
Use App\Http\Controllers\UserController;
use App\Http\Controllers\KolokiumController;
use App\Http\Controllers\PesertaKolokiumController;


// Auth
Route::post('/login', [AuthController::class, 'login']);

// Register (publik, admin tidak disediakan endpoint register karena biasanya dibuat manual/seeder)
Route::post('/register/dosen', [RegisterController::class, 'registerDosen']);
Route::post('/register/mahasiswa', [RegisterController::class, 'registerMahasiswa']);

Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::patch('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/foto', [UserController::class, 'uploadFotoProfil']);
    Route::post('/profile/tandatangan', [UserController::class, 'uploadTandaTangan']);
    
    Route::get('/images/{path}', [UserController::class, 'showImage'])
    ->where('path', '.*'); // biar bisa tangkap path dengan folder, mis: profile-photos/xxx.jpg
    
    // READ - semua role yang login
    Route::get('/kolokium/my', [KolokiumController::class, 'myKolokium']); // WAJIB di atas /{id}
    Route::get('/kolokium', [KolokiumController::class, 'index']);
    Route::get('/kolokium/{id}', [KolokiumController::class, 'show']);

    // CREATE - hanya mahasiswa 
    Route::post('/kolokium', [KolokiumController::class, 'store']);

    // PESERTA KOLOKIUM
    // Admin dan Dosen
    Route::get('/peserta-kolokium', [PesertaKolokiumController::class, 'index']);
    // Mahasiswa
    // get my kolokium peserta (kolokium yang diikuti)
    Route::get('/peserta-kolokium/my-kolokium', [PesertaKolokiumController::class, 'myKolokiumPeserta']);
    // get my peserta kolokium (peserta kolokium saya)
    Route::get('/peserta-kolokium/my-peserta', [PesertaKolokiumController::class, 'myPesertaKolokium']);
    Route::get('/peserta-kolokium/{id}', [PesertaKolokiumController::class, 'show']);
    Route::post('/peserta-kolokium', [PesertaKolokiumController::class, 'store']);
    Route::patch('/peserta-kolokium/{id}/status', [PesertaKolokiumController::class, 'updateStatus']);

    // UPDATE & DELETE - hanya admin
    Route::patch('/kolokium/{id}', [KolokiumController::class, 'update']);
    Route::delete('/kolokium/{id}', [KolokiumController::class, 'destroy']);
    
});