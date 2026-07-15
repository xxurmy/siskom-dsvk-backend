<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin
        User::create([
            'role'     => 'admin',
            'nama'     => 'Administrator',
            'username' => 'admin',
            'email'    => 'admin@kampus.ac.id',
            'password' => Hash::make('password123'),
            'status'   => true,
        ]);

        // 2. Dosen
        User::create([
            'role'        => 'dosen',
            'nama'        => 'Dr. Budi Santoso, M.Kom',
            'nip'         => '198501012010011001',
            'username'    => 'budisantoso',
            'prodi'       => 'Teknik Informatika',
            'email'       => 'budi.santoso@kampus.ac.id',
            'password'    => Hash::make('password123'),
            'status'      => true,
            'tandatangan' => null,
        ]);

        User::create([
            'role'        => 'dosen',
            'nama'        => 'Siti Aminah, S.T., M.T.',
            'nip'         => '199002152015022002',
            'username'    => 'sitiaminah',
            'prodi'       => 'Sistem Informasi',
            'email'       => 'siti.aminah@kampus.ac.id',
            'password'    => Hash::make('password123'),
            'status'      => true,
            'tandatangan' => null,
        ]);

        // 3. Mahasiswa
        User::create([
            'role'     => 'mahasiswa',
            'nama'     => 'Ahmad Fauzi',
            'nim'      => '21051001',
            'username' => 'ahmadfauzi',
            'prodi'    => 'Teknik Informatika',
            'email'    => 'ahmad.fauzi@mhs.kampus.ac.id',
            'password' => Hash::make('password123'),
            'status'   => true,
        ]);

        User::create([
            'role'     => 'mahasiswa',
            'nama'     => 'Rina Wulandari',
            'nim'      => '21051002',
            'username' => 'rinawulandari',
            'prodi'    => 'Sistem Informasi',
            'email'    => 'rina.wulandari@mhs.kampus.ac.id',
            'password' => Hash::make('password123'),
            'status'   => true,
        ]);
    }
}