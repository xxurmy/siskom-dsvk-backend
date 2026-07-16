<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PesertaKolokium extends Model
{
    use HasFactory;

    protected $table = 'peserta_kolokiums';

    protected $fillable = [
        'kolokium_id',
        'mahasiswa_id',
        'status',
    ];

    public function kolokium()
    {
        return $this->belongsTo(Kolokium::class, 'kolokium_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    public function kartuKolokium()
    {
        return $this->hasOne(KartuKolokium::class, 'peserta_kolokium_id');
    }
}
