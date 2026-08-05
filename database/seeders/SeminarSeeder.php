<?php
// database/seeders/SeminarSeeder.php
namespace Database\Seeders;

use App\Models\Seminar;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class SeminarSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $mahasiswaList = User::where('role', 'mahasiswa')->get();
        $dosenList     = User::where('role', 'dosen')->get();

        if ($mahasiswaList->isEmpty() || $dosenList->isEmpty()) {
            $this->command->warn('Belum ada data mahasiswa/dosen. Jalankan UserSeeder terlebih dahulu.');
            return;
        }

        $statusList  = ['pending', 'approved', 'rejected'];
        $ruanganList = ['Lab RPL', 'Ruang Sidang 1', 'Ruang Sidang 2', 'Aula Fasilkom', 'Ruang 301', 'Ruang 302'];

        // Seminar biasanya dijalani mahasiswa yang sudah lebih jauh progresnya
        // dibanding kolokium, jadi jumlah pesertanya kita buat lebih sedikit (~50%)
        $jumlahTerpilih = (int) ($mahasiswaList->count() * 0.5);
        $mahasiswaTerpilih = $mahasiswaList->shuffle()->take($jumlahTerpilih);

        foreach ($mahasiswaTerpilih as $mhs) {
            // Pilih 1-2 dosen pembimbing acak & berbeda untuk mahasiswa ini.
            // shuffle()->take() SELALU mengembalikan Collection, tidak seperti
            // random($n) yang perilakunya beda antara $n=1 dan $n>1.
            $jumlahPembimbing = $faker->numberBetween(1, 2);
            $pembimbingDipilih = $dosenList->shuffle()->take($jumlahPembimbing)->values();

            $namaDosenPembimbing = $pembimbingDipilih->pluck('nama')->implode(', ');

            $status = $faker->randomElement($statusList);

            // Moderator, tanggal, waktu, ruangan hanya terisi kalau seminar
            // sudah di-approve panitia (mirip logic di aplikasi aslinya)
            $moderator = null;
            $namaDosenModerator = null;
            $tanggal = null;
            $waktu = null;
            $ruangan = null;

            if ($status === 'approved') {
                $moderator = $dosenList->shuffle()->first();
                $namaDosenModerator = $moderator->nama;
                $tanggal = $faker->dateTimeBetween('+3 days', '+2 months')->format('Y-m-d');
                $waktu = $faker->randomElement(['08:00', '09:00', '10:00', '13:00', '14:00', '15:00']);
                $ruangan = $faker->randomElement($ruanganList);
            } elseif ($status === 'pending') {
                // Mahasiswa boleh isi tanggal/waktu usulan saat mendaftar,
                // tapi moderator & ruangan belum ditentukan panitia
                $tanggal = $faker->dateTimeBetween('+3 days', '+2 months')->format('Y-m-d');
                $waktu = $faker->randomElement(['08:00', '09:00', '10:00', '13:00', '14:00', '15:00']);
            }

            $seminar = Seminar::create([
                'mahasiswa_id'         => $mhs->id,
                'moderator_id'         => $moderator?->id,
                'nama'                 => $mhs->nama,
                'nim'                  => $mhs->nim,
                'prodi'                => $mhs->prodi,
                'namadosenpembimbing'  => $namaDosenPembimbing,
                'judul'                => $this->generateJudul($faker),
                'lokasi'               => $faker->randomElement($ruanganList),
                'tanggal'              => $tanggal,
                'waktu'                => $waktu,
                'namadosenmoderator'   => $namaDosenModerator,
                'ruangan'              => $ruangan,
                'status'               => $status,
                'jumlahforum'          => $faker->numberBetween(0, 15),
            ]);

            // Attach dosen pembimbing ke pivot table dengan urutan 1, 2, dst.
            $syncData = [];
            foreach ($pembimbingDipilih as $index => $dosen) {
                $syncData[$dosen->id] = ['urutan' => $index + 1];
            }
            $seminar->pembimbing()->sync($syncData);
        }

        $this->command->info("Berhasil membuat {$mahasiswaTerpilih->count()} data seminar.");
    }

    /**
     * Generate judul penelitian acak yang cukup masuk akal untuk data dummy.
     */
    private function generateJudul($faker): string
    {
        $topik = [
            'Sistem Informasi', 'Aplikasi Mobile', 'Website', 'Sistem Pendukung Keputusan',
            'Machine Learning', 'Data Mining', 'Sistem Pakar', 'Aplikasi Berbasis Web',
            'Sistem Monitoring', 'Rancang Bangun Aplikasi', 'Analisis Sentimen', 'Chatbot',
        ];
        $objek = [
            'Manajemen Perpustakaan', 'Penjualan Online', 'Absensi Karyawan', 'Inventaris Barang',
            'Pemesanan Tiket', 'Rekomendasi Produk', 'Deteksi Penyakit', 'Prediksi Harga',
            'Pengelolaan Keuangan', 'Pelayanan Pelanggan', 'Manajemen Proyek', 'Pengaduan Masyarakat',
        ];
        $metode = [
            'Metode Waterfall', 'Framework Laravel', 'Algoritma Naive Bayes', 'Metode Agile',
            'Algoritma K-Means', 'Metode Forward Chaining', 'Framework CodeIgniter', 'Algoritma C4.5',
        ];

        return sprintf(
            '%s %s Menggunakan %s Studi Kasus %s',
            $faker->randomElement($topik),
            $faker->randomElement($objek),
            $faker->randomElement($metode),
            $faker->company()
        );
    }
}