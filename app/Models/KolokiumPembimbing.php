<?php
// app/Models/KolokiumPembimbing.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class KolokiumPembimbing extends Pivot
{
    protected $table = 'kolokium_pembimbing';

    protected $fillable = [
        'kolokium_id',
        'user_id',
        'urutan',
    ];

    public $incrementing = true; // karena tabel punya kolom id sendiri

    public function kolokium()
    {
        return $this->belongsTo(Kolokium::class);
    }

    public function dosen()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}