<?php
namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Intervento;
use Illuminate\Support\Facades\Auth;

class EvadiInterventi extends Component
{
    public $vista = 'schede';
    public $dataSelezionata;
    public $interventi = [];
    public array $noteByIntervento = [];

    public function mount()
    {
        $this->dataSelezionata = now()->format('Y-m-d');
        $this->caricaInterventi();
    }

    public function caricaInterventi()
    {
        $user = Auth::user();
        $this->interventi = $user
            ? $user->interventi()
                ->with('cliente', 'sede')
                ->whereDate('data_intervento', $this->dataSelezionata)
                ->orderByRaw('intervento_tecnico.scheduled_start_at IS NULL')
                ->orderBy('intervento_tecnico.scheduled_start_at')
                ->orderBy('interventi.id')
                ->get()
            : collect();

        foreach ($this->interventi as $int) {
            if (!array_key_exists($int->id, $this->noteByIntervento)) {
                $this->noteByIntervento[$int->id] = $int->note;
            }
        }
    }

    

    public function getInterventiDelGiornoProperty()
    {
        return Intervento::with('cliente', 'sede')
            ->whereDate('data_intervento', $this->dataSelezionata)
            ->whereHas('tecnici', fn ($q) => $q->where('users.id', Auth::id()))
            ->get();
    }

    public function apriIntervento($id)
    {
        return redirect()->route('interventi.evadi.dettaglio', ['intervento' => $id]);
    }

    public function salvaNoteIntervento(int $id): void
    {
        if (!$id) return;
        $intervento = Intervento::find($id);
        if (!$intervento) return;
        $intervento->note = $this->noteByIntervento[$id] ?? null;
        $intervento->save();
        $this->dispatch('toast', type: 'success', message: 'Note intervento salvate');
    }

    public function render()
    {
        return view('livewire.interventi.evadi-interventi')->layout('layouts.app'); ;
    }
}
