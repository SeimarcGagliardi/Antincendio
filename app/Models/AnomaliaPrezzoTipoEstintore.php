<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomaliaPrezzoTipoEstintore extends Model
{
    protected $table = 'anomalia_prezzi_tipo_estintore';

    protected $fillable = [
        'anomalia_id',
        'tipo_estintore_id',
        'prezzo',
    ];

    protected $casts = [
        'prezzo' => 'decimal:2',
    ];

    public function anomalia(): BelongsTo
    {
        return $this->belongsTo(Anomalia::class, 'anomalia_id');
    }

    public function tipoEstintore(): BelongsTo
    {
        return $this->belongsTo(TipoEstintore::class, 'tipo_estintore_id');
    }
}

