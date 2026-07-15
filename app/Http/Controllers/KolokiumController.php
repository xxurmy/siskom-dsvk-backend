<?php
// app/Http/Controllers/KolokiumController.php

namespace App\Http\Controllers;

use App\Models\Kolokium;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KolokiumController extends Controller
{
    /**
     * READ - semua user yang sudah login
     */
    public function index(Request $request)
    {
        $query = Kolokium::with(['mahasiswa', 'pembimbing', 'moderator', 'pembahas']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('prodi')) {
            $query->where('prodi', $request->prodi);
        }

        return response()->json($query->latest()->paginate(10));
    }

    /**
     * GET MY KOLOKIUM - milik user yang sedang login
     */
    public function myKolokium(Request $request)
    {
        $user = $request->user();

        $query = Kolokium::with(['mahasiswa', 'pembimbing', 'moderator', 'pembahas'])
            ->where('mahasiswa_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(10));
    }

    public function show($id)
    {
        $kolokium = Kolokium::with(['mahasiswa', 'pembimbing', 'moderator', 'pembahas', 'pesertaKolokium'])
            ->find($id);

        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        return response()->json($kolokium);
    }

    /**
     * CREATE - user yang sudah login
     * mahasiswa_id otomatis dari user login, pembimbing wajib 1, boleh 2
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'pembimbing_id'       => 'required|array|min:1|max:2',
            'pembimbing_id.*'     => 'required|integer|exists:users,id|distinct',
            'moderator_id'        => 'nullable|exists:users,id',
            'pembahas_id'         => 'nullable|exists:users,id',
            'judul'               => 'required|string|max:255',
            'lokasi'              => 'nullable|string|max:255',
            'tanggal'             => 'nullable|date',
            'waktu'               => 'nullable|string|max:50',
            'namapembahas'        => 'nullable|string|max:255',
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

        $kolokium = Kolokium::create([
            'mahasiswa_id'         => $user->id,
            'nama'                => $user->nama,
            'nim'                 => $user->nim,
            'prodi'               => $user->prodi,
            'namadosenpembimbing' => $pembimbingUsers->pluck('nama')->implode(' & '),
            'moderator_id'        => $data['moderator_id'] ?? null,
            'pembahas_id'         => $data['pembahas_id'] ?? null,
            'judul'               => $data['judul'],
            'lokasi'              => $data['lokasi'] ?? null,
            'tanggal'             => $data['tanggal'] ?? null,
            'waktu'               => $data['waktu'] ?? null,
            'namapembahas'        => $data['namapembahas'] ?? null,
            'namadosenmoderator'  => $data['namadosenmoderator'] ?? null,
            'ruangan'             => $data['ruangan'] ?? null,
            'status'              => 'pending',
            'jumlahforum'         => 0,
        ]);

        $syncData = [];
        foreach ($pembimbingUsers as $index => $dosen) {
            $syncData[$dosen->id] = ['urutan' => $index + 1];
        }
        $kolokium->pembimbing()->sync($syncData);

        return response()->json([
            'message'  => 'Kolokium berhasil dibuat',
            'kolokium' => $kolokium->load('pembimbing'),
        ], 201);
    }

    /**
     * UPDATE - hanya admin
     */
    public function update(Request $request, $id)
    {
        $kolokium = Kolokium::find($id);

        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mahasiswa_id'          => 'sometimes|exists:users,id',
            'pembimbing_id'        => 'sometimes|array|min:1|max:2',
            'pembimbing_id.*'      => 'required_with:pembimbing_id|integer|exists:users,id|distinct',
            'moderator_id'         => 'sometimes|nullable|exists:users,id',
            'pembahas_id'          => 'sometimes|nullable|exists:users,id',
            'nama'                 => 'sometimes|string|max:255',
            'nim'                  => 'sometimes|string|max:50',
            'prodi'                => 'sometimes|string|max:100',
            'judul'                => 'sometimes|string|max:255',
            'lokasi'               => 'nullable|string|max:255',
            'tanggal'              => 'nullable|date',
            'waktu'                => 'nullable|string|max:50',
            'namapembahas'         => 'nullable|string|max:255',
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
            $kolokium->pembimbing()->sync($syncData);

            $data['namadosenpembimbing'] = $pembimbingUsers->pluck('nama')->implode(' & ');
            unset($data['pembimbing_id']);
        }

        $kolokium->update($data);

        return response()->json([
            'message'  => 'Kolokium berhasil diperbarui',
            'kolokium' => $kolokium->load('pembimbing'),
        ]);
    }

    /**
     * DELETE - hanya admin
     */
    public function destroy($id)
    {
        $kolokium = Kolokium::find($id);

        if (! $kolokium) {
            return response()->json(['message' => 'Kolokium tidak ditemukan'], 404);
        }

        $kolokium->delete(); // pivot ikut terhapus (cascadeOnDelete)

        return response()->json(['message' => 'Kolokium berhasil dihapus']);
    }
}