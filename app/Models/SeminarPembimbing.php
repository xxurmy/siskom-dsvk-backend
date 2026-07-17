<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SeminarPembimbing extends Pivot
{
    protected $table = 'seminar_pembimbing';

    protected $fillable = [
        'seminar_id',
        'user_id',
        'urutan',
    ];

    public $incrementing = true; // karena tabel punya kolom id sendiri

    public function seminar()
    {
        return $this->belongsTo(Seminar::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
