<?php
// database/seeders/UserSeeder.php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // locale Indonesia

        $prodiList = [
            'Teknik Informatika',
            'Sistem Informasi',
            'Teknik Elektro',
            'Manajemen',
            'Akuntansi',
        ];

        $hashedPassword = Hash::make('password123');

        // =========================
        // 1. Admin (10 data)
        // =========================
        User::create([
            'role'     => 'admin',
            'nama'     => 'Administrator',
            'username' => 'admin',
            'email'    => 'admin@kampus.ac.id',
            'password' => $hashedPassword,
            'status'   => true,
        ]);

        for ($i = 1; $i < 10; $i++) {
            $nama = $faker->name();
            User::create([
                'role'     => 'admin',
                'nama'     => $nama,
                'username' => 'admin' . ($i + 1),
                'email'    => 'admin' . ($i + 1) . '@kampus.ac.id',
                'password' => $hashedPassword,
                'status'   => true,
            ]);
        }

        // =========================
        // 2. Dosen (40 data)
        // =========================
        $dosenSample = [
            ['nama' => 'Dr. Budi Santoso, M.Kom', 'username' => 'budisantoso', 'prodi' => 'Teknik Informatika'],
            ['nama' => 'Siti Aminah, S.T., M.T.', 'username' => 'sitiaminah', 'prodi' => 'Sistem Informasi'],
        ];

        $tahunAwal = 1970;
        $tahunAkhir = 1995;

        foreach ($dosenSample as $index => $dosen) {
            User::create([
                'role'        => 'dosen',
                'nama'        => $dosen['nama'],
                'nip'         => (string) $faker->numberBetween($tahunAwal, $tahunAkhir) . $faker->numerify('####') . '10' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'username'    => $dosen['username'],
                'prodi'       => $dosen['prodi'],
                'email'       => $dosen['username'] . '@kampus.ac.id',
                'password'    => $hashedPassword,
                'status'      => true,
                'tandatangan' => null,
            ]);
        }

        for ($i = count($dosenSample); $i < 40; $i++) {
            $nama = $faker->name();
            $username = \Illuminate\Support\Str::slug($nama, '') . $i;
            $nip = $faker->numberBetween($tahunAwal, $tahunAkhir)
                . $faker->numerify('##')
                . $faker->numerify('##')
                . str_pad($i + 1, 6, '0', STR_PAD_LEFT);

            User::create([
                'role'        => 'dosen',
                'nama'        => $nama,
                'nip'         => $nip,
                'username'    => $username,
                'prodi'       => $faker->randomElement($prodiList),
                'email'       => $username . '@kampus.ac.id',
                'password'    => $hashedPassword,
                'status'      => true,
                'tandatangan' => null,
            ]);
        }

        // =========================
        // 3. Mahasiswa (100 data)
        // =========================
        $mahasiswaSample = [
            ['nama' => 'Ahmad Fauzi', 'nim' => '21051001', 'username' => 'ahmadfauzi', 'prodi' => 'Teknik Informatika'],
            ['nama' => 'Rina Wulandari', 'nim' => '21051002', 'username' => 'rinawulandari', 'prodi' => 'Sistem Informasi'],
        ];

        foreach ($mahasiswaSample as $mhs) {
            User::create([
                'role'     => 'mahasiswa',
                'nama'     => $mhs['nama'],
                'nim'      => $mhs['nim'],
                'username' => $mhs['username'],
                'prodi'    => $mhs['prodi'],
                'email'    => $mhs['username'] . '@mhs.kampus.ac.id',
                'password' => $hashedPassword,
                'status'   => true,
            ]);
        }

        for ($i = count($mahasiswaSample); $i < 100; $i++) {
            $nama = $faker->name();
            $username = \Illuminate\Support\Str::slug($nama, '') . $i;
            $angkatan = $faker->numberBetween(19, 24); // angkatan 2019–2024
            $nim = '2' . str_pad($angkatan, 2, '0', STR_PAD_LEFT) . '051' . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

            User::create([
                'role'     => 'mahasiswa',
                'nama'     => $nama,
                'nim'      => $nim,
                'username' => $username,
                'prodi'    => $faker->randomElement($prodiList),
                'email'    => $username . '@mhs.kampus.ac.id',
                'password' => $hashedPassword,
                'status'   => true,
            ]);
        }
    }
}