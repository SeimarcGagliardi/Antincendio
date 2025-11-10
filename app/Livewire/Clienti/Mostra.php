<?php

namespace App\Livewire\Clienti;

use App\Models\Cliente;
use Livewire\Component;

class Mostra extends Component
{
    public Cliente $cliente;
    public $modificaMesi = [];
    public $modificaMesiVisibile = [];
    public $mediaInterventiSenzaSede = null;

    public $fatturazione_tipo;
    public $mese_fatturazione;

    public bool $noteEdit = false;
    public ?string $note = null;
    public ?string $noteOriginal = null;
    
    public ?int $minuti_mese1 = null;
    public ?int $minuti_mese2 = null;

    
    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente->load([
            'sedi.interventi' => fn($q) => $q->whereNotNull('durata_effettiva'),
        ]);
        // in mount()
        $this->note = (string)($this->cliente->note ?? '');
        $this->mediaInterventiSenzaSede = $cliente
            ->interventi()
            ->whereNull('sede_id')
            ->whereNotNull('durata_effettiva')
            ->avg('durata_effettiva');

        foreach ($this->cliente->sedi as $sede) {
            $sede->media_durata_effettiva = $sede->interventi->avg('durata_effettiva');
        }
        $this->fatturazione_tipo = $this->cliente->fatturazione_tipo;
        $this->mese_fatturazione = $this->cliente->mese_fatturazione;

        $this->modificaMesi['cliente'] = array_fill_keys(
            $this->parseMesi($this->cliente->mesi_visita),
            true
        );
        
        foreach ($this->cliente->sedi as $sede) {
            $this->modificaMesi[$sede->id] = array_fill_keys(
                $this->parseMesi($sede->mesi_visita),
                true
            );

        }

        $this->modificaMesi['cliente'] = array_fill_keys(
            $this->normalizeMesiKeys($this->parseMesi($this->cliente->mesi_visita)),
            true
        );
        
        foreach ($this->cliente->sedi as $sede) {
            $this->modificaMesi[$sede->id] = array_fill_keys(
                $this->normalizeMesiKeys($this->parseMesi($sede->mesi_visita)),
                true
            );
        }
        
        // inizializza i minuti (se già presenti in DB li vedi subito)
        $this->minuti_mese1 = $this->cliente->minuti_intervento_mese1;
        $this->minuti_mese2 = $this->cliente->minuti_intervento_mese2;
        
    }
    public function salvaNote()
    {
        $this->validate([
            'note' => 'nullable|string|max:5000',
        ]);

        $val = trim((string)$this->note);
        $this->cliente->update(['note' => $val !== '' ? $val : null]);

        // rifletto subito nel model in memoria
        $this->cliente->note = $val !== '' ? $val : null;

        $this->dispatch('toast', type: 'success', message: 'Note cliente salvate.');
    }
    private function normalizeMesiKeys(array $raw): array
    {
        $map = ['gen'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'mag'=>5,'giu'=>6,'lug'=>7,'ago'=>8,'set'=>9,'ott'=>10,'nov'=>11,'dic'=>12];
        $out = [];

        // lista oppure oggetto {mese => bool}
        if (array_values($raw) === $raw) {
            foreach ($raw as $v) {
                $m = is_numeric($v) ? (int)$v : ($map[mb_strtolower((string)$v)] ?? null);
                if ($m >= 1 && $m <= 12) $out[] = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            }
        } else {
            foreach ($raw as $k => $v) {
                if (!$v) continue;
                $m = is_numeric($k) ? (int)$k : ($map[mb_strtolower((string)$k)] ?? null);
                if ($m >= 1 && $m <= 12) $out[] = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            }
        }

        $out = array_values(array_unique($out));
        sort($out);
        return $out; // "01","02",...
    }

    public function salvaFatturazione()
    {
        $this->validate([
            'fatturazione_tipo' => 'nullable|in:annuale,semestrale',
            'mese_fatturazione' => 'nullable|integer|min:1|max:12',
        ]);

        $this->cliente->update([
            'fatturazione_tipo' => $this->fatturazione_tipo,
            'mese_fatturazione' => $this->fatturazione_tipo === 'annuale' ? $this->mese_fatturazione : null,
        ]);

        $this->dispatch('toast', type: 'success', message: 'Fatturazione aggiornata con successo!');
    }

    private function parseMesi($value): array
    {
        if (is_array($value)) return $value;
    
        // Primo decode
        $decoded = json_decode($value, true);
    
        // Se ancora stringa JSON, decode una seconda volta
        if (is_string($decoded)) {
            return json_decode($decoded, true) ?? [];
        }
    
        return $decoded ?? [];
    }
    
    public function toggleMesiVisibili($chiave)
    {
        $this->modificaMesiVisibile[$chiave] = !($this->modificaMesiVisibile[$chiave] ?? false);
    }

    public function salvaMesi($sedeId = null)
    {
        $chiave = $sedeId ?? 'cliente';
        $selezionati = collect($this->modificaMesi[$chiave] ?? [])
                        ->filter(fn($v) => $v)
                        ->keys()
                        ->sort()
                        ->values()
                        ->toArray();

        $mesi = collect($selezionati)->sort()->values()->toArray();

        if ($sedeId) {
            $sede = \App\Models\Sede::find($sedeId);
            if ($sede && $sede->cliente_id === $this->cliente->id) {
                \Log::debug("Salvataggio mesi su Sede #{$sede->id}", $mesi);
                $sede->update(['mesi_visita' => $mesi]);
                \Log::debug("Valore mesi_visita dopo update: " . json_encode($sede->fresh()->mesi_visita));
            
            }
        } else {
            $this->cliente->update(['mesi_visita' => $mesi]);
        }

        $this->modificaMesiVisibile[$chiave] = false;
        $this->dispatch('toast', type: 'success',
                            message: 'Mesi Salvati con successo!');
    }

    public function vaiAiPresidi($sedeId = null)
    {
        return redirect()->route('presidi.gestione', [
            'clienteId' => $this->cliente->id,
            'sedeId' => $sedeId,
        ]);
    }

    // 🔧 NEW: mesi selezionati dal form (come array di interi 1..12)
    private function mesiSelezionati(string|int $chiave): array
    {
        $selez = collect($this->modificaMesi[$chiave] ?? [])
            ->filter(fn($v) => $v)
            ->keys()
            ->map(fn($k) => (int)$k)   // "03" => 3
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $selez;
    }
     // 🔽 NEW: salvataggio minuti client-level (abilitato solo se ci sono mesi)
     public function salvaMinutiCliente()
     {
         $mesi = $this->mesiSelezionati('cliente');
         $num  = count($mesi);
 
         if ($num === 0) {
             $this->dispatch('toast', type: 'error', message: 'Seleziona prima i mesi di visita.');
             return;
         }
 
         $rules = [
             'minuti_mese1' => 'required|integer|min:0|max:1440',
         ];
         if ($num >= 2) {
             $rules['minuti_mese2'] = 'required|integer|min:0|max:1440';
         } else {
             // se c’è un solo mese, il secondo è opzionale
             $rules['minuti_mese2'] = 'nullable|integer|min:0|max:1440';
         }
 
         $this->validate($rules);
 
         $this->cliente->update([
             'minuti_intervento_mese1' => $this->minuti_mese1,
             'minuti_intervento_mese2' => $num >= 2 ? $this->minuti_mese2 : null,
         ]);
 
         $this->dispatch('toast', type: 'success', message: 'Minuti visita aggiornati!');
     }
    public function render()
    {
         // 🔽 NEW: passiamo i mesi selezionati del cliente per etichette dinamiche
         $mesiClienteSelez = $this->mesiSelezionati('cliente'); // [3,9] ecc.

        return view('livewire.clienti.mostra', [
            'mediaInterventiSenzaSede' => $this->mediaInterventiSenzaSede
                ? round($this->mediaInterventiSenzaSede)
                : null,
                
            'mesiClienteSelezionati' => $mesiClienteSelez,
        ])->layout('layouts.app', ['title' => 'Dettaglio Cliente']);
    }
}
