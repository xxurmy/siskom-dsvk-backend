<?php
// app/Models/Kolokium.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\KolokiumPembimbing;

class Kolokium extends Model
{
    use HasFactory;
    protected $table = 'kolokiums';
    protected $fillable = [
        'mahasiswa_id',
        'moderator_id',
        'pembahas_id',
        'nama',
        'nim',
        'prodi',
        'namadosenpembimbing',
        'judul',
        'lokasi',
        'tanggal',
        'waktu',
        'namapembahas',
        'namadosenmoderator',
        'ruangan',
        'status',
        'jumlahforum',
    ];

    protected $casts = [
        'tanggal'     => 'date',
        'jumlahforum' => 'integer',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    // Many-to-many: 1 kolokium bisa punya 1-2 dosen pembimbing
    public function pembimbing()
    {
        return $this->belongsToMany(User::class, 'kolokium_pembimbing', 'kolokium_id', 'user_id')
            ->using(KolokiumPembimbing::class)
            ->withPivot('urutan')
            ->withTimestamps()
            ->orderBy('kolokium_pembimbing.urutan');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function pembahas()
    {
        return $this->belongsTo(User::class, 'pembahas_id');
    }

    public function pesertaKolokium()
    {
        return $this->hasMany(PesertaKolokium::class);
    }
}