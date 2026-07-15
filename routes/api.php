<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;

// Auth
Route::post('/login', [AuthController::class, 'login']);

// Register (publik, admin tidak disediakan endpoint register karena biasanya dibuat manual/seeder)
Route::post('/register/dosen', [RegisterController::class, 'registerDosen']);
Route::post('/register/mahasiswa', [RegisterController::class, 'registerMahasiswa']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});