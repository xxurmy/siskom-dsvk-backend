<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
Use App\Http\Controllers\UserController;
use App\Http\Controllers\KolokiumController;


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
    
    // READ - semua role yang login
    Route::get('/kolokium/my', [KolokiumController::class, 'myKolokium']); // WAJIB di atas /{id}
    Route::get('/kolokium', [KolokiumController::class, 'index']);
    Route::get('/kolokium/{id}', [KolokiumController::class, 'show']);

    // CREATE - hanya mahasiswa 
    Route::post('/kolokium', [KolokiumController::class, 'store']);

    // UPDATE & DELETE - hanya admin
    Route::patch('/kolokium/{id}', [KolokiumController::class, 'update']);
    Route::delete('/kolokium/{id}', [KolokiumController::class, 'destroy']);
    
});