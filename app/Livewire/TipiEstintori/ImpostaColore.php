<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\{TipoEstintore, Colore};

class ImpostaColore extends Component
{
    /** tipo_id => colore_id selezionato (in editing) */
    public array $selezioni = [];

    /** tipo_id => colore_id originale (per dirty check) */
    public array $originali = [];

    public $colori; // collection
    public $tipi;   // collection

    /** colore_id => hex */
    public array $hexById = [];

    /** colore_id => nome */
    public array $nomeById = [];

    public function mount(): void
    {
        $this->colori   = Colore::orderBy('nome')->get();
        $this->hexById  = $this->colori->pluck('hex', 'id')->toArray();
        $this->nomeById = $this->colori->pluck('nome', 'id')->toArray();

        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('tipo')->orderBy('kg')->get();

        foreach ($this->tipi as $t) {
            $this->selezioni[$t->id] = $t->colore_id;   // stato corrente
            $this->originali[$t->id] = $t->colore_id;   // originale
        }
    }

    /** Set immediato (anteprima) del colore selezionato per una riga */
    public function setColore(int $tipoId, ?int $coloreId): void
    {
        $this->selezioni[$tipoId] = $coloreId ?: null;
    }

    /** Id modificati (confronto int per evitare mismatch "1" vs 1) */
    public function getChangedIdsProperty(): array
    {
        $changed = [];
        foreach ($this->selezioni as $id => $val) {
            $new = $val !== null ? (int)$val : null;
            $old = isset($this->originali[$id]) ? (int)$this->originali[$id] : null;
            if ($new !== $old) $changed[] = (int)$id;
        }
        return $changed;
    }

    public function salva(): void
    {
        $changed = $this->changedIds;
        if (!$changed) {
            session()->flash('message', 'Nessuna modifica da salvare');
            return;
        }

        foreach ($changed as $id) {
            // niente mass-assignment: aggiorniamo a mano e salviamo
            $tipo = TipoEstintore::find($id);
            if (!$tipo) continue;
            $tipo->colore_id = $this->selezioni[$id] ?: null;
            $tipo->save();
        }

        // ricarica dati e azzera dirty
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('tipo')->orderBy('kg')->get();

        foreach ($this->tipi as $t) {
            $this->originali[$t->id] = $this->selezioni[$t->id] = $t->colore_id;
        }

        session()->flash('message', 'Colori salvati correttamente');
    }

    public function resetModifiche(): void
    {
        $this->selezioni = $this->originali;
    }

    public function render()
    {
        return view('livewire.tipi-estintori.imposta-colore', [
            'tipi'   => $this->tipi,
            'colori' => $this->colori,
        ]);
    }
}
