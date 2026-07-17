<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuSeminar extends Model
{
    use HasFactory;

    protected $table = 'kartu_seminars';

    protected $fillable = [
        'seminar_id',
        'pemrasaran_id',
        'moderator_id',
        'peserta_seminar_id',
        'forum_id',
        'tanggal',
        'waktu',
        'namapemrasaran',
        'nimpemrasaran',
        'prodi',
        'moderator',
        'namaforum',
        'nimforum',
        'tandatangandosen',
        'statusparaf',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function seminar()
    {
        return $this->belongsTo(Seminar::class, 'seminar_id');
    }

    public function pemrasaran()
    {
        return $this->belongsTo(User::class, 'pemrasaran_id');
    }

    public function moderatorUser()
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function forum()
    {
        return $this->belongsTo(User::class, 'forum_id');
    }

    public function pesertaSeminar()
    {
        return $this->belongsTo(PesertaSeminar::class, 'peserta_seminar_id');
    }
}
