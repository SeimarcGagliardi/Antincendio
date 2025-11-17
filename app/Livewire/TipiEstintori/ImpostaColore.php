<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    /** @var \Illuminate\Support\Collection|\App\Models\TipoEstintore[] */
    public $tipi;

    /** @var \Illuminate\Support\Collection|\App\Models\Colore[] */
    public $colori;

    /** @var array<int,int|null> [tipo_id => colore_id] */
    public $coloriSelezionati = [];

    public function mount(): void
    {
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

        $this->colori = Colore::orderBy('nome')->get();

        foreach ($this->tipi as $tipo) {
            $this->coloriSelezionati[$tipo->id] = $tipo->colore_id;
        }
    }

    public function salva(): void
    {
        if (empty($this->coloriSelezionati)) {
            return;
        }

        // rileggo solo i tipi interessati
        $tipi = TipoEstintore::whereIn('id', array_keys($this->coloriSelezionati))
            ->get();

        foreach ($tipi as $tipo) {
            $coloreId = $this->coloriSelezionati[$tipo->id] ?? null;

            // aggiorno solo se è cambiato
            if ($tipo->colore_id != $coloreId) {
                $tipo->colore_id = $coloreId ?: null;
                $tipo->save();
            }
        }

        // ricarico lista con relazione colore aggiornata
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

        session()->flash('message', 'Colori aggiornati correttamente.');
    }

    public function render()
    {
        return view('livewire.tipi-estintori.imposta-colore');
    }
}
