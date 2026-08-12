<?php
// app/Http/Controllers/SeminarController.php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpWord\TemplateProcessor;
use Carbon\Carbon;

class SeminarController extends Controller
{
    /**
     * Jumlah data per halaman default & batas maksimal, dipakai bareng
     * oleh index() & mySeminar() supaya konsisten dan tidak disalahgunakan
     * (mis. per_page=999999 yang bikin query berat).
     */
    private const DEFAULT_PER_PAGE = 10;
    private const MAX_PER_PAGE = 100;

    /**
     * Ambil & validasi nilai per_page dari query string.
     * Kalau tidak dikirim / tidak valid -> pakai DEFAULT_PER_PAGE.
     */
    private function resolvePerPage(Request $request): int
    {
        $validator = Validator::make($request->all(), [
            'per_page' => 'sometimes|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        if ($validator->fails()) {
            return self::DEFAULT_PER_PAGE;
        }

        return $validator->validated()['per_page'] ?? self::DEFAULT_PER_PAGE;
    }

    /**
     * READ - semua user yang sudah login
     *
     * Query params yang didukung:
     * - status   : filter status ('pending' | 'approved' | 'rejected')
     * - prodi    : filter prodi
     * - search   : cari bebas di kolom nama, nim, prodi, judul, dosen
     *              pembimbing, dosen moderator, lokasi, & ruangan
     * - per_page : jumlah data per halaman (default 10, maksimal 100)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $query = Seminar::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('prodi')) {
            $query->where('prodi', $request->prodi);
        }

        // SEARCH: satu kata kunci dicocokkan ke beberapa kolom sekaligus
        // (dipakai oleh fitur pencarian di halaman Jadwal Seminar, baik
        // untuk mahasiswa/dosen maupun admin).
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%")
                    ->orWhere('prodi', 'like', "%{$search}%")
                    ->orWhere('judul', 'like', "%{$search}%")
                    ->orWhere('namadosenpembimbing', 'like', "%{$search}%")
                    ->orWhere('namadosenmoderator', 'like', "%{$search}%")
                    ->orWhere('lokasi', 'like', "%{$search}%")
                    ->orWhere('ruangan', 'like', "%{$search}%");
            });
        }

        $perPage = $this->resolvePerPage($request);

        return response()->json(
            $query->orderBy('tanggal', 'desc')->paginate($perPage)->withQueryString()
        );
    }

    /**
     * GET MY SEMINAR - milik user yang sedang login
     *
     * Query params yang didukung:
     * - status   : filter status ('pending' | 'approved' | 'rejected')
     * - per_page : jumlah data per halaman (default 10, maksimal 100)
     */
    public function mySeminar(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $query = Seminar::query();

        if ($user->role === 'mahasiswa') {
            $query->where('mahasiswa_id', $user->id);
        } elseif ($user->role === 'dosen') {
            $query->where(function ($subQuery) use ($user) {
                $subQuery->where('moderator_id', $user->id)
                    ->orWhereHas('pembimbing', function ($pembimbingQuery) use ($user) {
                        $pembimbingQuery->where('users.id', $user->id);
                    });
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $this->resolvePerPage($request);

        return response()->json(
            $query->orderBy('tanggal', 'desc')->paginate($perPage)->withQueryString()
        );
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $seminar = Seminar::with('pembimbing')->find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        return response()->json($seminar);
    }

    /**
     * CREATE - user yang sudah login
     * mahasiswa_id otomatis dari user login, pembimbing wajib 1, boleh 2
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'mahasiswa') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'pembimbing_id'       => 'required|array|min:1|max:2',
            'pembimbing_id.*'     => 'required|integer|exists:users,id|distinct',
            'moderator_id'        => 'nullable|integer|exists:users,id',
            'judul'               => 'required|string|max:255',
            'lokasi'              => 'nullable|string|max:255',
            'tanggal'             => 'nullable|date|after:today',
            'waktu'               => 'nullable|string|max:50',
            'namadosenmoderator'  => 'nullable|string|max:255',
            'ruangan'             => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $pembimbingUsers = User::whereIn('id', $data['pembimbing_id'])
            ->where('role', 'dosen')
            ->get()
            ->sortBy(fn ($u) => array_search($u->id, $data['pembimbing_id']))
            ->values();

        if ($pembimbingUsers->count() !== count($data['pembimbing_id'])) {
            return response()->json([
                'message' => 'Semua pembimbing harus berasal dari akun dengan role dosen',
            ], 422);
        }

        $seminar = Seminar::create([
            'mahasiswa_id'        => $user->id,
            'nama'                => $user->nama,
            'nim'                 => $user->nim,
            'prodi'               => $user->prodi,
            'namadosenpembimbing' => $pembimbingUsers->pluck('nama')->implode(' & '),
            'moderator_id'        => $data['moderator_id'] ?? null,
            'judul'               => $data['judul'],
            'lokasi'              => $data['lokasi'] ?? null,
            'tanggal'             => $data['tanggal'] ?? null,
            'waktu'               => $data['waktu'] ?? null,
            'namadosenmoderator'  => $data['namadosenmoderator'] ?? null,
            'ruangan'             => $data['ruangan'] ?? null,
            'status'              => 'pending',
            'jumlahforum'         => 0,
        ]);

        $syncData = [];
        foreach ($pembimbingUsers as $index => $dosen) {
            $syncData[$dosen->id] = ['urutan' => $index + 1];
        }
        $seminar->pembimbing()->sync($syncData);

        return response()->json([
            'message'  => 'Seminar berhasil dibuat',
            'seminar'  => $seminar,
        ], 201);
    }

    /**
     * UPDATE - hanya admin
     */

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $seminar = Seminar::find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mahasiswa_id'          => 'sometimes|exists:users,id',
            'pembimbing_id'        => 'sometimes|array|min:1|max:2',
            'pembimbing_id.*'      => 'required_with:pembimbing_id|integer|exists:users,id|distinct',
            'moderator_id'         => 'sometimes|nullable|integer|exists:users,id',
            'nama'                 => 'sometimes|string|max:255',
            'nim'                  => 'sometimes|string|max:50',
            'prodi'                => 'sometimes|string|max:100',
            'judul'                => 'sometimes|string|max:255',
            'lokasi'               => 'nullable|string|max:255',
            'tanggal'              => 'nullable|date|after:today',
            'waktu'                => 'nullable|string|max:50',
            'namadosenmoderator'   => 'nullable|string|max:255',
            'ruangan'              => 'nullable|string|max:100',
            'status'               => 'sometimes|in:pending,approved,rejected',
            'jumlahforum'          => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $pembimbingIds = isset($data['pembimbing_id'])
            ? $data['pembimbing_id']
            : $seminar->pembimbing()->pluck('users.id')->all();

        if (array_key_exists('moderator_id', $data)) {
            if ($data['moderator_id'] === null) {
                $data['namadosenmoderator'] = null;
            } else {
                $moderatorUser = User::find($data['moderator_id']);

                if (! $moderatorUser || $moderatorUser->role !== 'dosen') {
                    return response()->json([
                        'message' => 'Moderator harus berasal dari akun dengan role dosen',
                    ], 422);
                }

                if (in_array($moderatorUser->id, $pembimbingIds, true)) {
                    return response()->json([
                        'message' => 'Moderator harus berbeda dari dosen pembimbing',
                    ], 422);
                }

                $data['namadosenmoderator'] = $moderatorUser->nama;
            }
        }

        if (isset($data['pembimbing_id'])) {
            $pembimbingUsers = User::whereIn('id', $data['pembimbing_id'])
                ->where('role', 'dosen')
                ->get()
                ->sortBy(fn ($u) => array_search($u->id, $data['pembimbing_id']))
                ->values();

            if ($pembimbingUsers->count() !== count($data['pembimbing_id'])) {
                return response()->json([
                    'message' => 'Semua pembimbing harus berasal dari akun dengan role dosen',
                ], 422);
            }

            $syncData = [];
            foreach ($pembimbingUsers as $index => $dosen) {
                $syncData[$dosen->id] = ['urutan' => $index + 1];
            }
            $seminar->pembimbing()->sync($syncData);

            $data['namadosenpembimbing'] = $pembimbingUsers->pluck('nama')->implode(' & ');
            unset($data['pembimbing_id']);
        }

        // VALIDASI TAMBAHAN: seminar tidak boleh di-approve kalau dosen
        // moderator dan ruangan belum lengkap — baik yang sudah tersimpan
        // sebelumnya di database, maupun yang baru dikirim di request ini.
        if (($data['status'] ?? null) === 'approved') {
            $finalModeratorId = array_key_exists('moderator_id', $data)
                ? $data['moderator_id']
                : $seminar->moderator_id;

            $finalRuangan = array_key_exists('ruangan', $data)
                ? $data['ruangan']
                : $seminar->ruangan;

            if (empty($finalModeratorId) || empty($finalRuangan)) {
                return response()->json([
                    'message' => 'Seminar tidak dapat disetujui karena data dosen moderator dan/atau ruangan belum lengkap',
                ], 422);
            }
        }

        $seminar->update($data);

        return response()->json([
            'message'  => 'Seminar berhasil diperbarui',
            'seminar'  => $seminar,
        ]);
    }

    /**
     * DELETE - hanya admin
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $seminar = Seminar::find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $seminar->delete(); // pivot ikut terhapus (cascadeOnDelete)

        return response()->json(['message' => 'Seminar berhasil dihapus']);
    }

    public function exportRekapitulasiNilai($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        $seminar = Seminar::with('pembimbing', 'moderator')->find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $jumlahPembimbing = $seminar->pembimbing->count();

        // Pilih template sesuai jumlah pembimbing
        $templateFile = $jumlahPembimbing >= 2
            ? 'rekap_nilai_seminar_2.docx'
            : 'rekap_nilai_seminar_1.docx';

        $templatePath = storage_path('app/templates/' . $templateFile);
        $template = new TemplateProcessor($templatePath);

        // Identitas
        $template->setValue('nama', $seminar->nama ?? '-');
        $template->setValue('nim', $seminar->nim ?? '-');
        // Format tanggal bahasa Indonesia
        Carbon::setLocale('id');

        $template->setValue(
            'hari_tanggal',
            $seminar->tanggal
                ? Carbon::parse($seminar->tanggal)->translatedFormat('l, d F Y')
                : '-'
        );

        // Format waktu: mulai - selesai (1 jam) WIB
        if ($seminar->waktu) {
            $mulai = Carbon::createFromFormat('H:i', $seminar->waktu);

            $selesai = $mulai->copy()->addHour();

            $waktuTempat =
            $mulai->format('H.i') .
            '-' .
            $selesai->format('H.i') .
            ' WIB / ' .
            ($seminar->lokasi ?? $seminar->ruangan ?? '-');
        } else {
            $waktuTempat = '- / ' . ($seminar->lokasi ?? $seminar->ruangan ?? '-');
        }

        $template->setValue(
            'waktu_tempat',
            $waktuTempat
        );
        $template->setValue('judul', $seminar->judul ?? '-');

        // Nama tim penilai (urut sesuai pivot 'urutan')
        $pembimbingUtama   = $seminar->pembimbing->firstWhere('pivot.urutan', 1);
        $pembimbingAnggota = $seminar->pembimbing->firstWhere('pivot.urutan', 2);

        $template->setValue('nama_pembimbing_utama', $pembimbingUtama->nama ?? '-');

        if ($jumlahPembimbing >= 2) {
            $template->setValue('nama_pembimbing_anggota', $pembimbingAnggota->nama ?? '-');
        }

        $template->setValue('nama_moderator', $seminar->moderator->nama ?? $seminar->namadosenmoderator ?? '-');

        $fileName = 'Rekap_Nilai_Seminar_' . str_replace(' ', '_', $seminar->nama) . '.docx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $template->saveAs($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }
    public function exportLembarPenilaian($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $seminar = Seminar::with('pembimbing', 'moderator')->find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $templatePath = storage_path('app/templates/formulir_lembar_penilaian_seminar.docx');
        $template = new TemplateProcessor($templatePath);

        // Identitas mahasiswa & seminar
        $template->setValue('nama', $seminar->nama ?? '-');
        $template->setValue('nim', $seminar->nim ?? '-');
        $template->setValue(
            'hari_tanggal',
            $seminar->tanggal ? $seminar->tanggal->locale('id')->translatedFormat('l, d F Y') : '-'
        );
        $template->setValue('waktu', $this->formatWaktuWib($seminar->waktu));
        $template->setValue('tempat', $seminar->ruangan ?? $seminar->lokasi ?? '-');
        $template->setValue('judul_skripsi', $seminar->judul ?? '-');

        // Tanggal tanda tangan = tanggal seminar berlangsung, bukan tanggal hari ini
        $template->setValue(
            'tanggal',
            $seminar->tanggal ? $seminar->tanggal->locale('id')->translatedFormat('d F Y') : '-'
        );

        // Nama tim penilai (urut sesuai pivot 'urutan')
        $pembimbingUtama   = $seminar->pembimbing->firstWhere('pivot.urutan', 1);
        $pembimbingAnggota = $seminar->pembimbing->firstWhere('pivot.urutan', 2);

        $template->setValue('nama_pembimbing_utama', $pembimbingUtama->nama ?? '-');
        $template->setValue('nama_pembimbing_anggota', $pembimbingAnggota->nama ?? '-');
        $template->setValue('nama_moderator', $seminar->moderator->nama ?? $seminar->namadosenmoderator ?? '-');

        $fileName = 'lembar_penilaian_seminar_' . str_replace(' ', '_', $seminar->nama) . '.docx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $template->saveAs($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    private function formatWaktuWib(?string $waktu): string
    {
        if (empty($waktu)) {
            return '-';
        }

        try {
            $normalized = str_replace('.', ':', trim($waktu));
            $mulai   = Carbon::createFromFormat('H:i', substr($normalized, 0, 5));
            $selesai = $mulai->copy()->addHour();

            return $mulai->format('H.i') . '-' . $selesai->format('H.i') . ' WIB';
        } catch (\Exception $e) {
            return $waktu;
        }
    }

    public function exportDaftarHadirSeminar($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $seminar = Seminar::with('pembimbing', 'moderator')->find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $templatePath = storage_path('app/templates/seminar-daftar-hadir.docx');
        $template = new TemplateProcessor($templatePath);

        // Identitas mahasiswa
        $template->setValue('nama', $seminar->nama ?? '-');
        $template->setValue('nim', $seminar->nim ?? '-');
        $template->setValue('program_studi', $seminar->prodi ?? '-');

        // Hari, tanggal, jam, ruang
        $template->setValue(
            'hari',
            $seminar->tanggal ? $seminar->tanggal->locale('id')->translatedFormat('l') : '-'
        );
        $template->setValue(
            'tanggal',
            $seminar->tanggal ? $seminar->tanggal->locale('id')->translatedFormat('d F Y') : '-'
        );
        $template->setValue('jam', $this->formatWaktuWib($seminar->waktu));
        $template->setValue('ruang', $seminar->ruangan ?? $seminar->lokasi ?? '-');

        $template->setValue('judul_skripsi', $seminar->judul ?? '-');

        // Nama tim penilai (urut sesuai pivot 'urutan')
        $pembimbingUtama   = $seminar->pembimbing->firstWhere('pivot.urutan', 1);
        $pembimbingAnggota = $seminar->pembimbing->firstWhere('pivot.urutan', 2);

        $template->setValue('nama_pembimbing_utama', $pembimbingUtama->nama ?? '-');
        $template->setValue('nama_pembimbing_anggota', $pembimbingAnggota->nama ?? '-');
        $template->setValue('moderator', $seminar->moderator->nama ?? $seminar->namadosenmoderator ?? '-');
        $template->setValue('nip', $seminar->moderator->nip ?? '-');

        $fileName = 'daftar_hadir_seminar_' . str_replace(' ', '_', $seminar->nama) . '.docx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $template->saveAs($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    public function exportBeritaAcaraSeminar($id, Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $seminar = Seminar::with('pembimbing', 'moderator')->find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        $jumlahPembimbing = $seminar->pembimbing->count();

        $templateFile = $jumlahPembimbing >= 2
            ? 'bap-seminar-2.docx'
            : 'bap-seminar-1.docx';

        $templatePath = storage_path('app/templates/' . $templateFile);
        $template = new TemplateProcessor($templatePath);

        $tanggalSeminar = $seminar->tanggal ?? Carbon::now();

        // Hari & tanggal (bahasa Indonesia)
        $template->setValue('hari', $tanggalSeminar->locale('id')->translatedFormat('l'));
        $template->setValue('tanggal', $tanggalSeminar->locale('id')->translatedFormat('d F Y'));

        // Tahun akademik otomatis dari tanggal seminar
        $tahunAkademik = $this->getTahunAkademik($tanggalSeminar);
        $template->setValue('tahun_akademik', $tahunAkademik);

        // Semester (angka), dihitung dari angkatan NIM vs tanggal seminar
        $angkatan = $this->getAngkatanFromNim($seminar->nim);
        $semester = $this->getSemesterAngka($tanggalSeminar, $angkatan);
        $template->setValue('semester', $semester ?? '-');

        // Identitas mahasiswa
        $template->setValue('nama', $seminar->nama ?? '-');
        $template->setValue('nim', $seminar->nim ?? '-');
        $template->setValue('judul_skripsi', $seminar->judul ?? '-');
        $template->setValue('waktu', $this->formatWaktuWib($seminar->waktu));
        $template->setValue('tempat', $seminar->ruangan ?? $seminar->lokasi ?? '-');

        // Tim penilai (urut sesuai pivot 'urutan')
        $pembimbingUtama   = $seminar->pembimbing->firstWhere('pivot.urutan', 1);
        $pembimbingAnggota = $seminar->pembimbing->firstWhere('pivot.urutan', 2);

        $template->setValue('nama_pembimbing_utama', $pembimbingUtama->nama ?? '-');
        $template->setValue('nip_pembimbing_utama', $pembimbingUtama->nip ?? '-');

        if ($jumlahPembimbing >= 2) {
            $template->setValue('nama_pembimbing_anggota', $pembimbingAnggota->nama ?? '-');
            $template->setValue('nip_pembimbing_anggota', $pembimbingAnggota->nip ?? '-');
        }

        $template->setValue('moderator', $seminar->moderator->nama ?? $seminar->namadosenmoderator ?? '-');
        $template->setValue('nip_moderator', $seminar->moderator->nip ?? '-');

        $fileName = 'bap_seminar_' . str_replace(' ', '_', $seminar->nama) . '.docx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (! file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $template->saveAs($tempPath);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Ambil tahun angkatan dari NIM.
     * Contoh: "E4401221027" -> 2 digit di posisi ke-6&7 ("22") -> 2022
     */
    private function getAngkatanFromNim(?string $nim): ?int
    {
        if (empty($nim) || strlen($nim) < 7) {
            return null;
        }

        $duaDigit = substr($nim, 5, 2);

        if (! ctype_digit($duaDigit)) {
            return null;
        }

        return 2000 + (int) $duaDigit;
    }

    /**
     * Hitung tahun akademik otomatis dari tanggal.
     * Bulan >= Juli -> Ganjil, format [tahun]/[tahun+1]
     * Bulan <  Juli -> Genap,  format [tahun-1]/[tahun]
     */
    private function getTahunAkademik(Carbon $tanggal): string
    {
        $tahun = (int) $tanggal->format('Y');
        $bulan = (int) $tanggal->format('n');

        return $bulan >= 7
            ? $tahun . '/' . ($tahun + 1)
            : ($tahun - 1) . '/' . $tahun;
    }

    /**
     * Hitung angka semester berdasarkan angkatan & tanggal seminar.
     * Semester Ganjil (bulan >= Juli): (tahun - angkatan) * 2 + 1
     * Semester Genap  (bulan <  Juli): (tahun - angkatan) * 2
     */
    private function getSemesterAngka(Carbon $tanggal, ?int $angkatan): ?int
    {
        if (! $angkatan) {
            return null;
        }

        $tahun = (int) $tanggal->format('Y');
        $bulan = (int) $tanggal->format('n');

        $selisihTahun = $tahun - $angkatan;

        return $bulan >= 7
            ? ($selisihTahun * 2) + 1
            : $selisihTahun * 2;
    }
}