<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Anomalia extends Model
{
    protected $fillable = [
        'categoria',
        'etichetta',
        'prezzo',
        'usa_prezzi_tipo_estintore',
        'usa_prezzi_tipo_presidio',
        'attiva',
    ];

    protected $casts = [
        'prezzo' => 'decimal:2',
        'usa_prezzi_tipo_estintore' => 'boolean',
        'usa_prezzi_tipo_presidio' => 'boolean',
        'attiva' => 'boolean',
    ];

    protected $table = 'anomalie';

    public function prezziTipoEstintore(): HasMany
    {
        return $this->hasMany(AnomaliaPrezzoTipoEstintore::class, 'anomalia_id');
    }

    public function prezziTipoPresidio(): HasMany
    {
        return $this->hasMany(AnomaliaPrezzoTipoPresidio::class, 'anomalia_id');
    }
}
