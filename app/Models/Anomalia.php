<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Anomalia extends Model
{
    protected $fillable = [
        'categoria',
        'etichetta',
        'prezzo',
        'attiva',
    ];

    protected $casts = [
        'prezzo' => 'decimal:2',
        'attiva' => 'boolean',
    ];

    protected $table = 'anomalie';
}
