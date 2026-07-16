<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KartuKolokium extends Model
{
    use HasFactory;

    protected $table = 'kartu_kolokiums';

    protected $fillable = [
        'kolokium_id',
        'pemrasaran_id',
        'moderator_id',
        'peserta_kolokium_id',
        'forum_id',
        'tanggal',
        'waktu',
        'namapemrasaran',
        'nimpemrasaran',
        'prodi',
        'moderator',
        'namaforum',
        'nimforum',
        'statusparaf',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function kolokium()
    {
        return $this->belongsTo(Kolokium::class, 'kolokium_id');
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

    public function pesertaKolokium()
    {
        return $this->belongsTo(PesertaKolokium::class, 'peserta_kolokium_id');
    }
}
