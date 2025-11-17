<?php

namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\TipoEstintore;
use App\Models\Colore;

class ImpostaColore extends Component
{
    

    public function mount(): void
    {
        $this->tipi = TipoEstintore::with('colore')
            ->orderBy('descrizione')
            ->get();

    

        foreach ($this->tipi as $tipo) {
            $this->coloriSelezionati[$tipo->id] = $tipo->colore_id;
        }
    }

    public function salva($idTipo,$idColore): void
    {
       
        $tipo = TipoEstintore::findOrFail($idTipo);
        $tipo->colore_id = $idColore;
        $tipo->save();

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
