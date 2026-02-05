<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;

class StatisticheAvanzate extends Component
{
    public string $graficoSelezionato = 'tecnici';
    public string $dataDa;
    public string $dataA;

    public array $summary = [];
    public array $charts = [];

    protected $listeners = ['refreshChartData' => '$refresh']; // per forzare rerender su aggiornamento filtri

    public function mount(): void
    {
        $this->dataDa = now()->startOfMonth()->format('Y-m-d');
        $this->dataA  = now()->endOfMonth()->format('Y-m-d');
        $this->loadData();
    }

    public function updatedGraficoSelezionato(): void
    {
        $this->dispatch('statistiche:refresh');
    }

    public function applyFilters(): void
    {
        $this->loadData();
    }

    public function preset(string $range): void
    {
        $today = Carbon::today();
        if ($range === 'month') {
            $this->dataDa = $today->copy()->startOfMonth()->format('Y-m-d');
            $this->dataA  = $today->copy()->endOfMonth()->format('Y-m-d');
        } elseif ($range === '30d') {
            $this->dataDa = $today->copy()->subDays(29)->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === '90d') {
            $this->dataDa = $today->copy()->subDays(89)->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === 'ytd') {
            $this->dataDa = $today->copy()->startOfYear()->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        } elseif ($range === 'year') {
            $this->dataDa = $today->copy()->subYear()->addDay()->format('Y-m-d');
            $this->dataA  = $today->format('Y-m-d');
        }
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.statistiche-avanzate');
    }

    private function loadData(): void
    {
        [$da, $a] = $this->normalizedRange();

        $baseInterventi = DB::table('interventi')
            ->whereBetween('data_intervento', [$da, $a])
            ->where('stato', 'Completato');

        $this->summary = [
            'interventi' => (clone $baseInterventi)->count(),
            'clienti' => (clone $baseInterventi)->distinct('cliente_id')->count('cliente_id'),
            'tecnici' => DB::table('intervento_tecnico')
                ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->where('interventi.stato', 'Completato')
                ->distinct('intervento_tecnico.user_id')
                ->count('intervento_tecnico.user_id'),
            'durata_media' => (int) round((clone $baseInterventi)->avg('durata_effettiva') ?? 0),
            'presidi' => DB::table('presidi_intervento')
                ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
                ->whereBetween('interventi.data_intervento', [$da, $a])
                ->where('interventi.stato', 'Completato')
                ->count(),
        ];

        $tecnici = DB::table('intervento_tecnico')
            ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->where('interventi.stato', 'Completato')
            ->selectRaw('users.name as label, COUNT(*) as interventi, SUM(COALESCE(interventi.durata_effettiva,0)) as minuti')
            ->groupBy('users.name')
            ->orderByDesc('interventi')
            ->limit(20)
            ->get();

        $clienti = DB::table('interventi')
            ->join('clienti', 'interventi.cliente_id', '=', 'clienti.id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw('clienti.nome as label, COUNT(*) as totale')
            ->groupBy('clienti.nome')
            ->orderByDesc('totale')
            ->limit(20)
            ->get();

        $durataTecnici = DB::table('intervento_tecnico')
            ->join('interventi', 'interventi.id', '=', 'intervento_tecnico.intervento_id')
            ->join('users', 'users.id', '=', 'intervento_tecnico.user_id')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->where('interventi.stato', 'Completato')
            ->selectRaw('users.name as label, ROUND(AVG(interventi.durata_effettiva), 0) as media')
            ->groupBy('users.name')
            ->orderByDesc('media')
            ->limit(20)
            ->get();

        $presidiCategoria = DB::table('presidi_intervento')
            ->join('presidi', 'presidi.id', '=', 'presidi_intervento.presidio_id')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw('presidi.categoria as label, COUNT(*) as totale')
            ->groupBy('presidi.categoria')
            ->orderByDesc('totale')
            ->get();

        $trendRaw = $this->trendInterventi($da, $a);
        $trendLabels = $trendRaw['labels'];
        $trendValues = $trendRaw['values'];

        $esiti = DB::table('presidi_intervento')
            ->join('interventi', 'interventi.id', '=', 'presidi_intervento.intervento_id')
            ->where('interventi.stato', 'Completato')
            ->whereBetween('interventi.data_intervento', [$da, $a])
            ->selectRaw('presidi_intervento.esito as label, COUNT(*) as totale')
            ->groupBy('presidi_intervento.esito')
            ->orderByDesc('totale')
            ->get();

        $this->charts = [
            'tecnici' => [
                'title' => 'Interventi per tecnico',
                'subtitle' => 'Numero interventi e minuti totali',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $tecnici->pluck('label')->values(),
                'datasets' => [
                    ['label' => 'Interventi', 'data' => $tecnici->pluck('interventi')->values(), 'backgroundColor' => '#EF4444'],
                    ['label' => 'Minuti', 'data' => $tecnici->pluck('minuti')->values(), 'backgroundColor' => '#3B82F6'],
                ],
                'table_headers' => ['Tecnico', 'Interventi', 'Minuti'],
                'table_rows' => $tecnici->map(fn($r) => [$r->label, (int)$r->interventi, (int)$r->minuti])->values(),
            ],
            'clienti' => [
                'title' => 'Interventi per cliente',
                'subtitle' => 'Top clienti nel periodo selezionato',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $clienti->pluck('label')->values(),
                'datasets' => [
                    ['label' => 'Interventi', 'data' => $clienti->pluck('totale')->values(), 'backgroundColor' => '#F97316'],
                ],
                'table_headers' => ['Cliente', 'Interventi'],
                'table_rows' => $clienti->map(fn($r) => [$r->label, (int)$r->totale])->values(),
            ],
            'durata' => [
                'title' => 'Durata media per tecnico',
                'subtitle' => 'Minuti medi per intervento',
                'type' => 'bar',
                'axis' => 'y',
                'labels' => $durataTecnici->pluck('label')->values(),
                'datasets' => [
                    ['label' => 'Minuti medi', 'data' => $durataTecnici->pluck('media')->values(), 'backgroundColor' => '#8B5CF6'],
                ],
                'table_headers' => ['Tecnico', 'Minuti medi'],
                'table_rows' => $durataTecnici->map(fn($r) => [$r->label, (int)$r->media])->values(),
            ],
            'categoria' => [
                'title' => 'Presidi per categoria',
                'subtitle' => 'Conteggio presidi verificati per categoria',
                'type' => 'doughnut',
                'labels' => $presidiCategoria->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Presidi',
                        'data' => $presidiCategoria->pluck('totale')->values(),
                        'backgroundColor' => ['#EF4444','#3B82F6','#F59E0B','#10B981','#8B5CF6','#64748B'],
                    ],
                ],
                'table_headers' => ['Categoria', 'Presidi'],
                'table_rows' => $presidiCategoria->map(fn($r) => [$r->label, (int)$r->totale])->values(),
            ],
            'trend' => [
                'title' => 'Trend interventi',
                'subtitle' => 'Andamento mensile nel periodo',
                'type' => 'line',
                'labels' => $trendLabels,
                'datasets' => [
                    [
                        'label' => 'Interventi',
                        'data' => $trendValues,
                        'borderColor' => '#0EA5E9',
                        'backgroundColor' => 'rgba(14,165,233,0.2)',
                        'tension' => 0.3,
                        'fill' => true,
                    ],
                ],
                'table_headers' => ['Mese', 'Interventi'],
                'table_rows' => collect($trendLabels)->map(function ($label, $idx) use ($trendValues) {
                    return [$label, (int)($trendValues[$idx] ?? 0)];
                })->values(),
            ],
            'esiti' => [
                'title' => 'Esiti interventi',
                'subtitle' => 'Distribuzione degli esiti sui presidi',
                'type' => 'doughnut',
                'labels' => $esiti->pluck('label')->values(),
                'datasets' => [
                    [
                        'label' => 'Esiti',
                        'data' => $esiti->pluck('totale')->values(),
                        'backgroundColor' => ['#22C55E','#F97316','#EF4444','#64748B'],
                    ],
                ],
                'table_headers' => ['Esito', 'Totale'],
                'table_rows' => $esiti->map(fn($r) => [ucfirst(str_replace('_',' ', $r->label)), (int)$r->totale])->values(),
            ],
        ];

        $this->dispatch('statistiche:refresh');
    }

    private function normalizedRange(): array
    {
        $da = Carbon::parse($this->dataDa)->startOfDay();
        $a  = Carbon::parse($this->dataA)->endOfDay();
        if ($da->gt($a)) {
            [$da, $a] = [$a, $da];
        }
        return [$da->format('Y-m-d'), $a->format('Y-m-d')];
    }

    private function trendInterventi(string $da, string $a): array
    {
        $driver = DB::getDriverName();
        $query = DB::table('interventi')
            ->where('stato', 'Completato')
            ->whereBetween('data_intervento', [$da, $a]);

        if ($driver === 'sqlite') {
            $query->selectRaw("strftime('%Y-%m', data_intervento) as mese, COUNT(*) as totale");
        } else {
            $query->selectRaw("DATE_FORMAT(data_intervento, '%Y-%m') as mese, COUNT(*) as totale");
        }

        $raw = $query->groupBy('mese')->orderBy('mese')->pluck('totale', 'mese');

        $start = Carbon::parse($da)->startOfMonth();
        $end = Carbon::parse($a)->startOfMonth();

        $labels = [];
        $values = [];
        foreach (CarbonPeriod::create($start, '1 month', $end) as $dt) {
            $key = $dt->format('Y-m');
            $labels[] = $dt->format('m/Y');
            $values[] = (int) ($raw[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}
