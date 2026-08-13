<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
Use App\Http\Controllers\UserController;
use App\Http\Controllers\KolokiumController;
use App\Http\Controllers\KartuKolokiumController;
use App\Http\Controllers\PesertaKolokiumController;
use App\Http\Controllers\SeminarController;
use App\Http\Controllers\PesertaSeminarController;
use App\Http\Controllers\KartuSeminarController;


// Auth
Route::post('/login', [AuthController::class, 'login']);

// Register (publik, admin tidak disediakan endpoint register karena biasanya dibuat manual/seeder)
Route::post('/register/dosen', [RegisterController::class, 'registerDosen']);
Route::post('/register/mahasiswa', [RegisterController::class, 'registerMahasiswa']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1'); // maksimal 3 request per menit
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::prefix('auth')->middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'profile']);
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::get('/dosen', [UserController::class, 'dosenList']);
    Route::get('/mahasiswa', [UserController::class, 'mahasiswaList']);
    Route::patch('/profile', [UserController::class, 'updateProfile']);
    Route::post('/profile/foto', [UserController::class, 'uploadFotoProfil']);
    Route::post('/profile/tandatangan', [UserController::class, 'uploadTandaTangan']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/images/{path}', [UserController::class, 'showImage'])
    ->where('path', '.*'); // biar bisa tangkap path dengan folder, mis: profile-photos/xxx.jpg
    
    // READ - semua role yang login
    Route::get('/kolokium/my', [KolokiumController::class, 'myKolokium']); // WAJIB di atas /{id}
    Route::get('/kolokium', [KolokiumController::class, 'index']);
    Route::get('/kolokium/{id}', [KolokiumController::class, 'show']);

    // export file kolokium, hanya admin yang bisa
    Route::get('/kolokium/{id}/export-rekapitulasi-nilai-kolokium', [KolokiumController::class, 'exportRekapitulasiNilai']);
    Route::get('/kolokium/{id}/export-lembar-penilaian', [KolokiumController::class, 'exportLembarPenilaian']);
    Route::get('/kolokium/{id}/export-daftar-hadir-kolokium', [KolokiumController::class, 'exportDaftarHadirKolokium']);
    Route::get('/kolokium/{id}/export-berita-acara-kolokium', [KolokiumController::class, 'exportBeritaAcaraKolokium']);;

    // CREATE - hanya mahasiswa 
    Route::post('/kolokium', [KolokiumController::class, 'store']);

    // SEMINAR
    Route::get('/seminar/my', [SeminarController::class, 'mySeminar']);
    Route::get('/seminar', [SeminarController::class, 'index']);
    Route::get('/seminar/{id}', [SeminarController::class, 'show']);
    Route::post('/seminar', [SeminarController::class, 'store']);

    // export file seminar, hanya admin yang bisa
    Route::get('/seminar/{id}/export-rekapitulasi-nilai-seminar', [SeminarController::class, 'exportRekapitulasiNilai']);
    Route::get('/seminar/{id}/export-lembar-penilaian', [SeminarController::class, 'exportLembarPenilaian']);
    Route::get('/seminar/{id}/export-daftar-hadir-seminar', [SeminarController::class, 'exportDaftarHadirSeminar']);
    Route::get('/seminar/{id}/export-berita-acara-seminar', [SeminarController::class, 'exportBeritaAcaraSeminar']);;
    Route::get('/seminar/{id}/export-kesediaan-moderator', [SeminarController::class, 'exportSuratKesediaanModerator']);

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

    // KARTU KOLOKIUM
    Route::get('/kartu-kolokium/my', [KartuKolokiumController::class, 'my']);
    Route::get('/kartu-kolokium/kolokium/{kolokiumId}', [KartuKolokiumController::class, 'byKolokium']);
    Route::patch('/kartu-kolokium/bulk-status-paraf', [KartuKolokiumController::class, 'bulkUpdateStatusParaf']);
    Route::patch('/kartu-kolokium/{id}/status-paraf', [KartuKolokiumController::class, 'updateStatusParaf']);

    // PESERTA SEMINAR
    Route::get('/peserta-seminar', [PesertaSeminarController::class, 'index']);
    Route::get('/peserta-seminar/my-seminar', [PesertaSeminarController::class, 'mySeminarPeserta']);
    Route::get('/peserta-seminar/my-peserta', [PesertaSeminarController::class, 'myPesertaSeminar']);
    Route::get('/peserta-seminar/{id}', [PesertaSeminarController::class, 'show']);
    Route::post('/peserta-seminar', [PesertaSeminarController::class, 'store']);
    Route::patch('/peserta-seminar/{id}/status', [PesertaSeminarController::class, 'updateStatus']);

    // KARTU SEMINAR
    Route::get('/kartu-seminar/my', [KartuSeminarController::class, 'my']);
    Route::get('/kartu-seminar/seminar/{seminarId}', [KartuSeminarController::class, 'bySeminar']);
    Route::patch('/kartu-seminar/bulk-status-paraf', [KartuSeminarController::class, 'bulkUpdateStatusParaf']);
    Route::patch('/kartu-seminar/{id}/status-paraf', [KartuSeminarController::class, 'updateStatusParaf']);

    // UPDATE & DELETE - hanya admin
    Route::patch('/kolokium/{id}', [KolokiumController::class, 'update']);
    Route::delete('/kolokium/{id}', [KolokiumController::class, 'destroy']);
    Route::patch('/seminar/{id}', [SeminarController::class, 'update']);
    Route::delete('/seminar/{id}', [SeminarController::class, 'destroy']);
});