<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    /**
     * Elenco tipologie di estintori
     *
     * @var \Illuminate\Support\Collection|\App\Models\TipoEstintore[]
     */
    public $tipi;

    /**
     * Elenco colori disponibili
     *
     * @var \Illuminate\Support\Collection|\App\Models\Colore[]
     */
    public $colori;

    /**
     * Mappa [ tipo_id => colore_id ]
     *
     * @var array<int,int|null>
     */
    public $coloriSelezionati = [];

    public function mount(): void
    {
        // carica tipi e colori
        $this->tipi   = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

        $this->colori = Colore::orderBy('nome')->get();

        // inizializza la mappa [tipo_id => colore_id]
        foreach ($this->tipi as $tipo) {
            $this->coloriSelezionati[$tipo->id] = $tipo->colore_id;
        }
    }

    public function salva(): void
    {
        foreach ($this->tipi as $tipo) {
            $coloreId = $this->coloriSelezionati[$tipo->id] ?? null;

            if ($tipo->colore_id != $coloreId) {
                $tipo->colore_id = $coloreId ?: null;
                $tipo->save();
            }
        }

        // ricarico l’elenco aggiornato con la relazione colore
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

        session()->flash('message', 'Colori aggiornati correttamente.');
    }

    public function render()
    {
        // le proprietà pubbliche ($tipi, $colori, $coloriSelezionati) sono
        // automaticamente disponibili nella view
        return view('livewire.tipi-estintori.imposta-colore');
    }
}
