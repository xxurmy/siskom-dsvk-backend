<?php

namespace App\Http\Controllers;

use App\Models\Seminar;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeminarController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
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

        return response()->json($query->latest()->paginate(10));
    }

    public function mySeminar(Request $request)
    {
        $user = $request->user();
        if (! $user) {
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

        return response()->json($query->latest()->paginate(10));
    }

    public function show($id, Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 404);
        }

        $seminar = Seminar::find($id);

        if (! $seminar) {
            return response()->json(['message' => 'Seminar tidak ditemukan'], 404);
        }

        return response()->json($seminar);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
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
            'pembahas_id'         => 'nullable|integer|exists:users,id',
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

        if (! empty($data['pembahas_id'])) {
            $pembahasUser = User::find($data['pembahas_id']);

            if (! $pembahasUser || $pembahasUser->role !== 'mahasiswa') {
                return response()->json([
                    'message' => 'Pembahas harus berasal dari akun dengan role mahasiswa',
                ], 422);
            }

            $data['namapembahas'] = $pembahasUser->nama;
        }

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
        $seminar->pembimbing()->sync($syncData);

        return response()->json([
            'message'  => 'Seminar berhasil dibuat',
            'seminar' => $seminar,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
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
            'pembahas_id'          => 'sometimes|nullable|integer|exists:users,id',
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

        $pembimbingIds = isset($data['pembimbing_id'])
            ? $data['pembimbing_id']
            : $seminar->pembimbing()->pluck('users.id')->all();

        if (array_key_exists('pembahas_id', $data)) {
            if ($data['pembahas_id'] === null) {
                $data['namapembahas'] = null;
            } else {
                $pembahasUser = User::find($data['pembahas_id']);

                if (! $pembahasUser || $pembahasUser->role !== 'mahasiswa') {
                    return response()->json([
                        'message' => 'Pembahas harus berasal dari akun dengan role mahasiswa',
                    ], 422);
                }

                $data['namapembahas'] = $pembahasUser->nama;
            }
        }

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

        $seminar->update($data);

        return response()->json([
            'message'  => 'Seminar berhasil diperbarui',
            'seminar' => $seminar,
        ]);
    }

    public function destroy($id, Request $request)
    {
        $user = $request->user();
        if (! $user) {
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

        $seminar->delete();

        return response()->json(['message' => 'Seminar berhasil dihapus']);
    }
}
