<?php

namespace App\Services\Interventi;

use App\Models\Anomalia;
use App\Models\Intervento;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Schema;

class RapportinoInterventoService
{
    public const KIND_CLIENTE = 'cliente';
    public const KIND_INTERNO = 'interno';

    public function normalizeKind(?string $kind): string
    {
        $kind = strtolower(trim((string) $kind));
        return $kind === self::KIND_INTERNO ? self::KIND_INTERNO : self::KIND_CLIENTE;
    }

    public function buildDataByInterventoId(int $interventoId): array
    {
        $intervento = Intervento::with($this->relations())->findOrFail($interventoId);

        return $this->buildData($intervento);
    }

    public function buildData(Intervento $intervento): array
    {
        $intervento->loadMissing($this->relations());

        $ordiniSvc = app(OrdinePreventivoService::class);

        $ordinePreventivo = $ordiniSvc->caricaOrdineApertoPerCliente((string) ($intervento->cliente?->codice_esterno ?? ''));
        $righeIntervento = $ordiniSvc->buildRigheIntervento($intervento->presidiIntervento);
        $confrontoOrdine = $ordiniSvc->buildConfronto(
            $ordinePreventivo['rows'] ?? [],
            $righeIntervento['rows'] ?? []
        );
        $anomaliaQuery = Anomalia::query()->select(['id', 'etichetta']);
        if (Schema::hasColumn('anomalie', 'prezzo')) {
            $anomaliaQuery->addSelect('prezzo');
        }

        $anomalieRiepilogo = $ordiniSvc->buildAnomalieSummaryFromPresidiIntervento(
            $intervento->presidiIntervento,
            $anomaliaQuery
                ->get()
                ->mapWithKeys(fn (Anomalia $anomalia) => [
                    $anomalia->id => [
                        'etichetta' => (string) $anomalia->etichetta,
                        'prezzo' => (float) ($anomalia->prezzo ?? 0),
                    ],
                ])
                ->toArray()
        );
        $hasAnomaliaItemsTable = Schema::hasTable('presidio_intervento_anomalie');

        return [
            'intervento' => $intervento,
            'ordinePreventivo' => $ordinePreventivo,
            'righeIntervento' => $righeIntervento,
            'confrontoOrdine' => $confrontoOrdine,
            'anomalieRiepilogo' => $anomalieRiepilogo,
            'hasAnomaliaItemsTable' => $hasAnomaliaItemsTable,
            'riepilogoOrdine' => [
                'righe_intervento' => $righeIntervento['rows'] ?? [],
                'presidi_senza_codice' => $righeIntervento['missing_mapping'] ?? [],
                'confronto' => $confrontoOrdine,
                'anomalie' => $anomalieRiepilogo,
            ],
        ];
    }

    public function buildPdf(string $kind, array $data)
    {
        $kind = $this->normalizeKind($kind);

        return Pdf::loadView($this->viewFor($kind), $data)->setPaper('a4');
    }

    public function renderPdfOutput(string $kind, array $data): string
    {
        return $this->buildPdf($kind, $data)->output();
    }

    public function filename(string $kind, Intervento $intervento): string
    {
        $kind = $this->normalizeKind($kind);
        $suffix = $kind === self::KIND_INTERNO ? 'interno' : 'cliente';
        return "rapportino_intervento_{$intervento->id}_{$suffix}.pdf";
    }

    private function viewFor(string $kind): string
    {
        return $kind === self::KIND_INTERNO
            ? 'pdf.rapportino-intervento-interno'
            : 'pdf.rapportino-intervento-cliente';
    }

    private function relations(): array
    {
        $relations = [
            'cliente',
            'sede',
            'tecnici',
            'presidiIntervento.presidio.tipoEstintore',
            'presidiIntervento.presidio.idranteTipoRef',
            'presidiIntervento.presidio.portaTipoRef',
        ];

        if (Schema::hasTable('presidio_intervento_anomalie')) {
            $relations[] = 'presidiIntervento.anomalieItems.anomalia';
        }

        return $relations;
    }
}
