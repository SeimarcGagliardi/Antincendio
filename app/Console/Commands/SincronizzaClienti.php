<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Cliente;
use App\Models\Sede;

class SincronizzaClienti extends Command
{
    protected $signature = 'sincronizza:clienti {--lookback=1 : Giorni di retrodatazione per sicurezza}';
    protected $description = 'Sincronizza clienti e sedi da MSSQL a MySQL (basata su codice_esterno)';
    private ?array $tabPagaColumns = null;

    public function handle()
    {
        $this->info("Inizio sincronizzazione clienti e sedi...");

        $lookbackDays = (int) $this->option('lookback');
        $lastAnagra = $this->applyLookback($this->getLastSync('anagra'), $lookbackDays);
        $lastDestdiv = $this->applyLookback($this->getLastSync('destdiv'), $lookbackDays);
        $anagraCodPagaCol = $this->resolveAnagraCodPagaColumn();

        $clientiQuery = $this->buildAnagraQuery($anagraCodPagaCol);

        if ($lastAnagra) {
            $clientiQuery->where('a.an_ultagg', '>', $lastAnagra);
        }

        $clienti = $clientiQuery->orderBy('a.an_ultagg')->get();

        $clientiCount = 0;
        $maxAnagra = $lastAnagra;

        foreach ($clienti as $c) {
            Cliente::updateOrCreate(
                ['codice_esterno' => $c->an_conto],
                $this->mapClientePayload($c)
            );

            $clientiCount++;
            $maxAnagra = $this->maxTimestamp($maxAnagra, $c->an_ultagg);
        }

        $this->setLastSync('anagra', $maxAnagra);

        $destdivUltaggCol = $this->resolveDestdivUltaggColumn();
        $destdivHasUltagg = $destdivUltaggCol ? $this->destdivHasAnyUltagg($destdivUltaggCol) : false;

        $destdivQuery = DB::connection('sqlsrv')->table('destdiv');
        if ($destdivHasUltagg && $lastDestdiv) {
            $destdivQuery->where($destdivUltaggCol, '>', $lastDestdiv);
        }

        if ($destdivHasUltagg) {
            $destdivQuery->orderBy($destdivUltaggCol);
        }

        $destdiv = $destdivQuery->get();
        $sediCount = 0;
        $maxDestdiv = $lastDestdiv;

        if ($destdiv->isNotEmpty()) {
            $conti = $destdiv->pluck('dd_conto')->unique()->values();
            $clientiLocal = Cliente::whereIn('codice_esterno', $conti)->get()->keyBy('codice_esterno');

            $missingConti = $conti->diff($clientiLocal->keys());
            if ($missingConti->isNotEmpty()) {
                $missing = $this->buildAnagraQuery($anagraCodPagaCol)
                    ->whereIn('a.an_conto', $missingConti)
                    ->get();

                foreach ($missing as $c) {
                    $cliente = Cliente::updateOrCreate(
                        ['codice_esterno' => $c->an_conto],
                        $this->mapClientePayload($c)
                    );
                    $clientiLocal->put($c->an_conto, $cliente);
                }
            }

            foreach ($destdiv as $s) {
                $cliente = $clientiLocal->get($s->dd_conto);
                if (!$cliente) {
                    continue;
                }

                Sede::updateOrCreate(
                    [
                        'codice_esterno' => $s->dd_conto . '-' . $s->dd_coddest,
                        'cliente_id' => $cliente->id,
                    ],
                    [
                        'nome' => trim($s->dd_nomdest . ' ' . $s->dd_nomdest2),
                        'indirizzo' => $s->dd_inddest,
                        'cap' => $s->dd_capdest,
                        'citta' => $s->dd_locdest,
                        'provincia' => $s->dd_prodest,
                    ]
                );

                $sediCount++;
                if ($destdivHasUltagg) {
                    $maxDestdiv = $this->maxTimestamp($maxDestdiv, $s->{$destdivUltaggCol} ?? null);
                }
            }
        }

        if ($destdivHasUltagg) {
            $this->setLastSync('destdiv', $maxDestdiv);
        }

        $this->info("Sincronizzazione completata. Clienti: {$clientiCount}, Sedi: {$sediCount}.");
        return Command::SUCCESS;
    }

    private function getLastSync(string $key): ?Carbon
    {
        $row = DB::table('sync_statuses')->where('key', $key)->first();
        if (!$row || !$row->last_synced_at) {
            return null;
        }

        return Carbon::parse($row->last_synced_at);
    }

    private function setLastSync(string $key, ?Carbon $timestamp): void
    {
        if (!$timestamp) {
            return;
        }

        DB::table('sync_statuses')->updateOrInsert(
            ['key' => $key],
            [
                'last_synced_at' => $timestamp,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    private function maxTimestamp(?Carbon $current, $candidate): ?Carbon
    {
        if (!$candidate) {
            return $current;
        }

        $parsed = $candidate instanceof Carbon ? $candidate : Carbon::parse($candidate);

        if (!$current || $parsed->greaterThan($current)) {
            return $parsed;
        }

        return $current;
    }

    private function applyLookback(?Carbon $lastSync, int $days): ?Carbon
    {
        if (!$lastSync || $days <= 0) {
            return $lastSync;
        }

        return $lastSync->copy()->subDays($days);
    }

    private function buildAnagraQuery(?string $anagraCodPagaCol)
    {
        $query = DB::connection('sqlsrv')
            ->table('anagra as a')
            ->where('a.an_tipo', 'C')
            ->select('a.*');

        $tabPagaCols = $this->resolveTabPagaColumns();
        if ($anagraCodPagaCol && !empty($tabPagaCols['code'])) {
            $query->leftJoin('tabpaga as tp', "tp.{$tabPagaCols['code']}", '=', "a.{$anagraCodPagaCol}")
                ->addSelect(DB::raw("a.{$anagraCodPagaCol} as an_codpaga_sync"));

            if (!empty($tabPagaCols['desc'])) {
                $query->addSelect(DB::raw("tp.{$tabPagaCols['desc']} as tb_despaga_sync"));
            }
        }

        return $query;
    }

    private function mapClientePayload(object $c): array
    {
        $payload = [
            'nome' => trim(((string) $c->an_descr1) . ' ' . ((string) $c->an_descr2)),
            'p_iva' => $c->an_pariva,
            'email' => $c->an_email,
            'telefono' => $c->an_telef,
            'indirizzo' => $c->an_indir,
            'cap' => $c->an_cap,
            'citta' => $c->an_citta,
            'provincia' => $c->an_prov,
        ];
        // NB: non includere "note" qui: le note anagrafica gestite nel gestionale
        // non devono essere sovrascritte dalla sincronizzazione Business.

        $formaCodice = $this->normalizePaymentCode($c);
        $formaDescrizione = trim((string) ($this->readObjectValue($c, [
            'tb_despaga_sync',
            'tb_despaga',
            'tb_despag',
        ]) ?? ''));
        if ($formaDescrizione === '') {
            $formaDescrizione = null;
        }

        if ($formaCodice === null && $formaDescrizione === null) {
            return $payload;
        }

        if ($formaDescrizione === null && $formaCodice === 40) {
            $formaDescrizione = 'ALLA CONSEGNA';
        }

        $payload['forma_pagamento_codice'] = $formaCodice;
        $payload['forma_pagamento_descrizione'] = $formaDescrizione;
        $payload['richiede_pagamento_manutentore'] = $formaCodice === 40;

        return $payload;
    }

    private function normalizePaymentCode(object $c): ?int
    {
        $candidate = $this->readObjectValue($c, [
            'an_codpaga_sync',
            'an_codpaga',
            'an_codpag',
        ]);
        return is_numeric($candidate) ? (int) $candidate : null;
    }

    private function resolveAnagraCodPagaColumn(): ?string
    {
        try {
            $columns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('anagra');
        } catch (\Throwable $e) {
            return null;
        }

        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[strtolower($col)] = $col;
        }

        foreach (['an_codpaga', 'an_codpag'] as $candidate) {
            if (isset($columnMap[$candidate])) {
                return $columnMap[$candidate];
            }
        }

        return null;
    }

    private function resolveTabPagaColumns(): array
    {
        if ($this->tabPagaColumns !== null) {
            return $this->tabPagaColumns;
        }

        try {
            $columns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('tabpaga');
        } catch (\Throwable $e) {
            $this->tabPagaColumns = ['code' => null, 'desc' => null];
            return $this->tabPagaColumns;
        }

        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[strtolower($col)] = $col;
        }

        $this->tabPagaColumns = [
            'code' => $columnMap['tb_codpaga'] ?? $columnMap['tb_codpag'] ?? null,
            'desc' => $columnMap['tb_despaga'] ?? $columnMap['tb_despag'] ?? null,
        ];

        return $this->tabPagaColumns;
    }

    private function readObjectValue(object $row, array $candidates)
    {
        $raw = (array) $row;
        $map = [];
        foreach ($raw as $key => $value) {
            $map[mb_strtolower((string) $key)] = $value;
        }

        foreach ($candidates as $candidate) {
            $key = mb_strtolower((string) $candidate);
            if (array_key_exists($key, $map)) {
                return $map[$key];
            }
        }

        return null;
    }

    private function resolveDestdivUltaggColumn(): ?string
    {
        try {
            $columns = DB::connection('sqlsrv')->getSchemaBuilder()->getColumnListing('destdiv');
        } catch (\Throwable $e) {
            return null;
        }

        $columnMap = [];
        foreach ($columns as $col) {
            $columnMap[strtolower($col)] = $col;
        }
        foreach (['dd_ultagg', 'an_ultagg', 'ultagg', 'dd_dataagg'] as $candidate) {
            if (isset($columnMap[$candidate])) {
                return $columnMap[$candidate];
            }
        }

        return null;
    }

    private function destdivHasAnyUltagg(string $column): bool
    {
        try {
            return DB::connection('sqlsrv')->table('destdiv')
                ->whereNotNull($column)
                ->limit(1)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
