<?php
namespace App\Livewire\TipiEstintori;

use Livewire\Component;
use App\Models\{TipoEstintore, Colore};

class ImpostaColore extends Component
{
    public array $selezioni = []; // [tipo_id => colore_id]
    public $colori = [];

    public function mount(): void
    {
        $this->colori = Colore::orderBy('nome')->get();
        $tipi = TipoEstintore::select('id','colore_id')->get();
        foreach ($tipi as $t) $this->selezioni[$t->id] = $t->colore_id;
    }

    public function updatedSelezioni($value, $key): void
    {
        // $key = tipo_id
        $tipo = TipoEstintore::find((int)$key);
        if (!$tipo) return;

        $coloreId = $value ? (int)$value : null;
        // opzionale: validazione che $coloreId esista
        $tipo->update(['colore_id' => $coloreId]);
        $this->dispatch('notify', body: 'Colore aggiornato');
    }

    public function render()
    {
        $tipi = TipoEstintore::with('colore')
            ->orderBy('tipo')->orderBy('kg')->get();

        return view('livewire.tipi-estintori.imposta-colore', [
            'tipi' => $tipi,
            'colori' => $this->colori,
        ]);
    }
}
