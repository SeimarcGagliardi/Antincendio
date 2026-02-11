<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 13px; color: #222; }
        header { border-bottom: 2px solid #b30000; margin-bottom: 18px; padding-bottom: 10px; }
        header h1 { color: #b30000; font-size: 20px; margin: 0; }
        header p { margin: 0; font-size: 12px; }
        h2 { color: #b30000; border-bottom: 1px solid #ddd; padding-bottom: 2px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; margin-bottom: 15px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; font-size: 12px; }
        th { background-color: #f8f8f8; font-weight: bold; text-transform: uppercase; }
        .note { border: 1px solid #ddd; padding: 10px; background-color: #fefefe; }
        .firma { margin-top: 36px; text-align: center; }
        .firma label { font-weight: bold; display: block; margin-bottom: 10px; color: #444; }
        .firma img { max-width: 300px; border: 1px solid #ccc; }
        .dati-cliente { line-height: 1.4; }
    </style>
</head>
<body>
    <header>
        <h1>ANTINCENDIO LUGHESE SRL</h1>
        <p>Via G. Ricci Curbastro 54/56 – 48020 S.Agata sul Santerno (RA)</p>
    </header>

    <h2>Rapportino Intervento Cliente</h2>

    <table>
        <tr>
            <th>Data Intervento</th>
            <td>{{ \Carbon\Carbon::parse($intervento->data_intervento)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <th>Tecnici</th>
            <td>{{ $intervento->tecnici->pluck('name')->join(', ') }}</td>
        </tr>
        <tr>
            <th>Durata Effettiva</th>
            <td>{{ (int)($intervento->durata_effettiva ?? 0) }} minuti</td>
        </tr>
    </table>

    <h2>Dati Cliente</h2>
    <div class="dati-cliente">
        <strong>Ragione Sociale:</strong> {{ $intervento->cliente->nome }}<br>
        <strong>Indirizzo:</strong>
        {{ $intervento->sede->indirizzo ?? $intervento->cliente->indirizzo }},
        {{ $intervento->sede->cap ?? $intervento->cliente->cap }}
        {{ $intervento->sede->citta ?? $intervento->cliente->citta }} ({{ $intervento->sede->provincia ?? $intervento->cliente->provincia }})<br>
        <strong>Sede:</strong> {{ $intervento->sede->nome ?? 'Sede Principale' }}
    </div>

    <h2>Presidi Verificati</h2>
    <table>
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Progressivo</th>
                <th>Ubicazione</th>
                <th>Esito</th>
                <th>Anomalie</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach($intervento->presidiIntervento as $pi)
                <tr>
                    <td>{{ $pi->presidio->categoria }}</td>
                    <td>{{ $pi->presidio->progressivo }}</td>
                    <td>{{ $pi->presidio->ubicazione }}</td>
                    <td>{{ strtoupper($pi->esito) }}</td>
                    <td>
                        @php
                            $anomaliaItems = collect();
                            if (!empty($hasAnomaliaItemsTable)) {
                                $anomaliaItems = $pi->relationLoaded('anomalieItems')
                                    ? $pi->anomalieItems
                                    : $pi->anomalieItems()->with('anomalia')->get();
                            }
                        @endphp
                        @if($anomaliaItems->isNotEmpty())
                            @foreach($anomaliaItems as $item)
                                {{ $item->anomalia?->etichetta ?? '-' }}@if(!$loop->last), @endif
                            @endforeach
                        @else
                            @foreach($pi->anomalie as $anomalia)
                                {{ $anomalia->etichetta }}@if(!$loop->last), @endif
                            @endforeach
                        @endif
                    </td>
                    <td>{{ $pi->note }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Riepilogo Anomalie</h2>
    <div class="note">
        <strong>Totale:</strong> {{ $anomalieRiepilogo['totale'] ?? 0 }}<br>
        <strong>Riparate:</strong> {{ $anomalieRiepilogo['riparate'] ?? 0 }}<br>
        <strong>Da preventivare:</strong> {{ $anomalieRiepilogo['preventivo'] ?? 0 }}
    </div>

    @php $noteIntervento = $intervento->note ?? $intervento->note_generali ?? null; @endphp
    @if($noteIntervento)
        <h2>Note Intervento</h2>
        <div class="note">{{ $noteIntervento }}</div>
    @endif

    <div class="firma">
        <label>Firma del Cliente per accettazione</label>
        @if($intervento->firma_cliente_base64)
            <img src="{{ $intervento->firma_cliente_base64 }}" alt="Firma Cliente">
        @else
            <p><em>Firma non disponibile</em></p>
        @endif
    </div>
</body>
</html>
