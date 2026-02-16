<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnomaliaPrezzoTipoPresidio extends Model
{
    protected $table = 'anomalia_prezzi_tipo_presidio';

    protected $fillable = [
        'anomalia_id',
        'tipo_presidio_id',
        'prezzo',
    ];

    protected $casts = [
        'prezzo' => 'decimal:2',
    ];

    public function anomalia(): BelongsTo
    {
        return $this->belongsTo(Anomalia::class, 'anomalia_id');
    }

    public function tipoPresidio(): BelongsTo
    {
        return $this->belongsTo(TipoPresidio::class, 'tipo_presidio_id');
    }
}

