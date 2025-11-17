<?php

namespace App\Livewire\Interventi;

use Livewire\Component;
use App\Models\Cliente;
use App\Models\Sede;
use App\Models\User;
use App\Models\Intervento;
use App\Models\Presidio;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FormPianificazioneIntervento extends Component
{
    public $clienteId;
    public $sedeId;
    public $dataIntervento;
    public $tecnici = [];
    public $tecniciDisponibili = [];

    public $meseSelezionato;
    public $annoSelezionato;

    public $zonaFiltro = '';
    public array $zoneDisponibili = [];   // 👈 array tipizzato

    public $clientiInScadenza = [];
    public $clientiConInterventiEsistenti = [];
    
    protected $listeners = ['setMeseAnno'];

    public function mount()
    {
        $oggi = now();
        $this->meseSelezionato = $oggi->month;
        $this->annoSelezionato = $oggi->year;

        $this->tecniciDisponibili = User::whereHas('ruoli', function ($q) {
            $q->where('nome', 'Tecnico');
        })->get();

        // 👇 ZONE DA CLIENTI + SEDI
        $this->caricaZoneDisponibili();
    }

    /**
     * Carica le zone distinte da CLIENTI e SEDI.
     */
    protected function caricaZoneDisponibili(): void
    {
        $zoneClienti = Cliente::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $zoneSedi = Sede::query()
            ->whereNotNull('zona')
            ->where('zona', '!=', '')
            ->pluck('zona');

        $this->zoneDisponibili = $zoneClienti
            ->merge($zoneSedi)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    public function applicaFiltri()
    {
        $this->clientiInScadenza = $this->getClientiInScadenzaProperty();
        $this->clientiConInterventiEsistenti = $this->getClientiConInterventiEsistentiProperty();
    }

    public function interventoRegistrato($clienteId, $sedeId = null): bool
    {
        // 🔧 ho solo lasciato la parte utile, eliminando il codice dopo il return
        return Intervento::where('cliente_id', $clienteId)
            ->when($sedeId !== null, fn($q) => $q->where('sede_id', $sedeId))
            ->when($sedeId === null, fn($q) => $q->whereNull('sede_id'))
            ->whereMonth('data_intervento', $this->meseSelezionato)
            ->whereYear('data_intervento', $this->annoSelezionato)
            ->exists();
    }

    public function setMeseAnno($mese, $anno)
    {
        $this->meseSelezionato = (int) $mese;
        $this->annoSelezionato = (int) $anno;

        // Se vuoi che le zone cambino al cambiare del mese/anno
        // puoi eventualmente ricaricarle qui (se metti logiche aggiuntive):
        // $this->caricaZoneDisponibili();
    }

    // ... TUTTO IL RESTO DELLA CLASSE COME HAI GIÀ ...

    public function render()
    {
        return view('livewire.interventi.form-pianificazione-intervento', [
            'clientiInScadenza' => $this->clientiInScadenza,
            'clientiConInterventiEsistenti' => $this->clientiConInterventiEsistenti,
        ]);
    }
}
