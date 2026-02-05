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

    public function handle()
    {
        $this->info("Inizio sincronizzazione clienti e sedi...");

        $lookbackDays = (int) $this->option('lookback');
        $lastAnagra = $this->applyLookback($this->getLastSync('anagra'), $lookbackDays);
        $lastDestdiv = $this->applyLookback($this->getLastSync('destdiv'), $lookbackDays);

        $clientiQuery = DB::connection('sqlsrv')
            ->table('anagra')
            ->where('an_tipo', 'C');

        if ($lastAnagra) {
            $clientiQuery->where('an_ultagg', '>', $lastAnagra);
        }

        $clienti = $clientiQuery->orderBy('an_ultagg')->get();

        $clientiCount = 0;
        $maxAnagra = $lastAnagra;

        foreach ($clienti as $c) {
            $cliente = Cliente::updateOrCreate(
                ['codice_esterno' => $c->an_conto],
                [
                    'nome' => trim($c->an_descr1 . ' ' . $c->an_descr2),
                    'p_iva' => $c->an_pariva,
                    'email' => $c->an_email,
                    'telefono' => $c->an_telef,
                    'indirizzo' => $c->an_indir,
                    'cap' => $c->an_cap,
                    'citta' => $c->an_citta,
                    'provincia' => $c->an_prov,
                ]
            );

            $clientiCount++;
            $maxAnagra = $this->maxTimestamp($maxAnagra, $c->an_ultagg);
        }

        $this->setLastSync('anagra', $maxAnagra);

        $destdivQuery = DB::connection('sqlsrv')->table('destdiv');
        if ($lastDestdiv) {
            $destdivQuery->where('an_ultagg', '>', $lastDestdiv);
        }

        $destdiv = $destdivQuery->orderBy('an_ultagg')->get();
        $sediCount = 0;
        $maxDestdiv = $lastDestdiv;

        if ($destdiv->isNotEmpty()) {
            $conti = $destdiv->pluck('dd_conto')->unique()->values();
            $clientiLocal = Cliente::whereIn('codice_esterno', $conti)->get()->keyBy('codice_esterno');

            $missingConti = $conti->diff($clientiLocal->keys());
            if ($missingConti->isNotEmpty()) {
                $missing = DB::connection('sqlsrv')
                    ->table('anagra')
                    ->where('an_tipo', 'C')
                    ->whereIn('an_conto', $missingConti)
                    ->get();

                foreach ($missing as $c) {
                    $cliente = Cliente::updateOrCreate(
                        ['codice_esterno' => $c->an_conto],
                        [
                            'nome' => trim($c->an_descr1 . ' ' . $c->an_descr2),
                            'p_iva' => $c->an_pariva,
                            'email' => $c->an_email,
                            'telefono' => $c->an_telef,
                            'indirizzo' => $c->an_indir,
                            'cap' => $c->an_cap,
                            'citta' => $c->an_citta,
                            'provincia' => $c->an_prov,
                        ]
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
                $maxDestdiv = $this->maxTimestamp($maxDestdiv, $s->an_ultagg);
            }
        }

        $this->setLastSync('destdiv', $maxDestdiv);

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
}
