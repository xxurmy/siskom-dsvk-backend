<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seminar extends Model
{
    use HasFactory;

    protected $table = 'seminars';

    protected $fillable = [
        'mahasiswa_id',
        'moderator_id',
        'nama',
        'nim',
        'prodi',
        'namadosenpembimbing',
        'judul',
        'lokasi',
        'tanggal',
        'waktu',
        'namadosenmoderator',
        'ruangan',
        'status',
        'catatan',
        'jumlahforum',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'jumlahforum' => 'integer',
    ];

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function pembimbing()
    {
        return $this->belongsToMany(User::class, 'seminar_pembimbing', 'seminar_id', 'user_id')
            ->using(SeminarPembimbing::class)
            ->withPivot('urutan')
            ->withTimestamps()
            ->orderBy('seminar_pembimbing.urutan');
    }

    public function moderator()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function pesertaSeminar()
    {
        return $this->hasMany(PesertaSeminar::class);
    }

    public function kartuSeminar()
    {
        return $this->hasMany(KartuSeminar::class);
    }
    
    public function getJumlahforumAttribute(): int
    {
        return $this->pesertaSeminar()
            ->where('status', 'hadir')
            ->count();
    }
}
