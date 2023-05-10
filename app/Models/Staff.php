<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'price', 'description'
    ];

    public function peminjaman()
    {
        return $this->hasMany(Peminjaman::class);
    }
}
