<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
<<<<<<< HEAD
    /** @var \Illuminate\Support\Collection|\App\Models\TipoEstintore[] */
    public $tipi;

    /** @var \Illuminate\Support\Collection|\App\Models\Colore[] */
    public $colori;

    /** @var array<int,int|null> [tipo_id => colore_id] */
    public array $coloriSelezionati = [];
=======
    
>>>>>>> ec811cd4cee747b1f7aa6de8a1cbb29c7a3cbbb7

    public function mount(): void
    {
        $this->caricaDati();
    }

    protected function caricaDati(): void
    {
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

    

        $this->coloriSelezionati = [];
        foreach ($this->tipi as $tipo) {
            $this->coloriSelezionati[$tipo->id] = $tipo->colore_id;
        }
    }

    public function salva($idTipo,$idColore): void
    {
        if (empty($this->coloriSelezionati)) {
            return;
        }

        // rileggo solo i tipi che stiamo gestendo
        $tipi = TipoEstintore::whereIn('id', array_keys($this->coloriSelezionati))->get();

        foreach ($tipi as $tipo) {
            $coloreId = $this->coloriSelezionati[$tipo->id] ?? null;

            if ($tipo->colore_id != $coloreId) {
                $tipo->colore_id = $coloreId ?: null;
                $tipo->save();
            }
        }

        // ricarico tutto aggiornato
        $this->caricaDati();

        session()->flash('message', 'Colori aggiornati correttamente.');
    }

    public function render()
    {
        $colori = Colore::orderBy('nome')->get();
        $tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();
        return view('livewire.tipi-estintori.imposta-colore',['colori' => $colori, 'tipi' => $tipi]);
    }
}
