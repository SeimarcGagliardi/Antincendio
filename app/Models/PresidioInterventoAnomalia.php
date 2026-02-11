<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresidioInterventoAnomalia extends Model
{
    protected $table = 'presidio_intervento_anomalie';

    protected $fillable = [
        'presidio_intervento_id',
        'anomalia_id',
        'riparata',
    ];

    protected $casts = [
        'riparata' => 'boolean',
    ];

    public function presidioIntervento(): BelongsTo
    {
        return $this->belongsTo(PresidioIntervento::class, 'presidio_intervento_id');
    }

    public function anomalia(): BelongsTo
    {
        return $this->belongsTo(Anomalia::class, 'anomalia_id');
    }
}
