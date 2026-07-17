<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PesertaSeminar extends Model
{
    use HasFactory;

    protected $table = 'peserta_seminars';

    protected $fillable = [
        'seminar_id',
        'mahasiswa_id',
        'status',
    ];

    public function seminar()
    {
        return $this->belongsTo(Seminar::class, 'seminar_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function kartuSeminar()
    {
        return $this->hasOne(KartuSeminar::class, 'peserta_seminar_id');
    }
}
