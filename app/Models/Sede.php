<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedi';

    protected $fillable = [
        'cliente_id',
        'nome',
        'indirizzo',
        'citta',
        'cap',
        'provincia',
        'codice_esterno',
        'minuti_intervento',
        'minuti_intervento_mese1',
        'minuti_intervento_mese2',
        'mesi_visita',
        'zona',
    ];
    protected $casts = [
        'mesi_visita' => 'array',
    ];
    

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
    public function interventi()
    {
        return $this->hasMany(\App\Models\Intervento::class);
    }
    public function presidi()
    {
        return $this->hasMany(Presidio::class,'sede_id');
    }

    /**
     * Normalizza mesi_visita in array di mesi [1..12], ordinati e max 2.
     */
    private function normalizzaMesiVisita(): array
    {
        $raw = $this->mesi_visita ?? [];
        $map = ['gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];

        $out = [];
        if (array_values($raw) === $raw) {
            foreach ($raw as $v) {
                $out[] = is_numeric($v) ? (int) $v : ($map[mb_strtolower((string) $v)] ?? null);
            }
        } else {
            foreach ($raw as $k => $v) {
                if (!$v) continue;
                $out[] = is_numeric($k) ? (int) $k : ($map[mb_strtolower((string) $k)] ?? null);
            }
        }

        $out = array_values(array_filter(array_unique($out), fn ($m) => $m >= 1 && $m <= 12));
        sort($out);
        return array_slice($out, 0, 2);
    }

    public function indiceVisitaPerMese(int $mese): ?int
    {
        $attivi = $this->normalizzaMesiVisita();
        if (!$attivi) return null;

        $pos = array_search($mese, $attivi, true);
        return ($pos === false) ? null : ($pos + 1);
    }

    public function minutiPerMese(int $mese): ?int
    {
        $indice = $this->indiceVisitaPerMese($mese) ?? 1;
        return $indice === 2
            ? ($this->minuti_intervento_mese2 ?? $this->minuti_intervento_mese1 ?? $this->minuti_intervento)
            : ($this->minuti_intervento_mese1 ?? $this->minuti_intervento);
    }
}
