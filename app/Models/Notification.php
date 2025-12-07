<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    // ✅ On autorise tout en écriture pour l’instant (simple & efficace)
    protected $guarded = [];

    // ✅ Casts : data = array, read_at = datetime
    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
