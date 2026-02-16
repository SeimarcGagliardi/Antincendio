<?php

namespace App\Services\Clienti;

use Illuminate\Support\Facades\DB;
use Throwable;

class BusinessFormaPagamentoService
{
    private const CONN = 'sqlsrv';

    public function leggiPerConto(?string $conto): array
    {
        $conto = trim((string) $conto);
        if ($conto === '') {
            return $this->result(false, null, null, false, 'Codice conto non valorizzato.');
        }

        try {
            $anagraCodeCol = $this->resolveColumn('anagra', ['an_codpaga', 'an_codpag']);
            $tabpagaCols = $this->resolveTabPagaColumns();

            $query = DB::connection(self::CONN)
                ->table('anagra as a')
                ->where('a.an_tipo', 'C')
                ->where('a.an_conto', $conto)
                ->select('a.an_conto');

            if ($anagraCodeCol) {
                $query->addSelect(DB::raw("a.{$anagraCodeCol} as an_codpaga_sync"));
            }

            if ($anagraCodeCol && !empty($tabpagaCols['code'])) {
                $query->leftJoin('tabpaga as tp', "tp.{$tabpagaCols['code']}", '=', "a.{$anagraCodeCol}");
                if (!empty($tabpagaCols['desc'])) {
                    $query->addSelect(DB::raw("tp.{$tabpagaCols['desc']} as tb_despaga_sync"));
                }
            }

            $row = $query->first();
            if (!$row) {
                return $this->result(false, null, null, false, "Conto {$conto} non trovato in anagra.");
            }

            $code = $this->toInt($this->rowValue($row, [
                'an_codpaga_sync',
                'an_codpaga',
                'an_codpag',
            ]));

            $desc = $this->toStringOrNull($this->rowValue($row, [
                'tb_despaga_sync',
                'tb_despaga',
                'tb_despag',
            ]));

            if ($desc === null && $code !== null) {
                $desc = $this->lookupDescrizioneFromTabPaga($code, $tabpagaCols);
            }

            if ($desc === null && $code === 40) {
                $desc = 'ALLA CONSEGNA';
            }

            $richiede = $code === 40;
            return $this->result(true, $code, $desc, $richiede, null);
        } catch (Throwable $e) {
            return $this->result(false, null, null, false, $e->getMessage());
        }
    }

    private function lookupDescrizioneFromTabPaga(int $code, array $tabpagaCols): ?string
    {
        if (empty($tabpagaCols['code']) || empty($tabpagaCols['desc'])) {
            return null;
        }

        try {
            $row = DB::connection(self::CONN)
                ->table('tabpaga')
                ->where($tabpagaCols['code'], $code)
                ->select($tabpagaCols['desc'])
                ->first();
        } catch (Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return $this->toStringOrNull($this->rowValue($row, [$tabpagaCols['desc']]));
    }

    private function resolveTabPagaColumns(): array
    {
        $code = $this->resolveColumn('tabpaga', ['tb_codpaga', 'tb_codpag']);
        $desc = $this->resolveColumn('tabpaga', ['tb_despaga', 'tb_despag']);

        return [
            'code' => $code,
            'desc' => $desc,
        ];
    }

    private function resolveColumn(string $table, array $candidates): ?string
    {
        try {
            $columns = DB::connection(self::CONN)->getSchemaBuilder()->getColumnListing($table);
        } catch (Throwable $e) {
            return null;
        }

        $map = [];
        foreach ($columns as $column) {
            $map[mb_strtolower((string) $column)] = (string) $column;
        }

        foreach ($candidates as $candidate) {
            $key = mb_strtolower((string) $candidate);
            if (isset($map[$key])) {
                return $map[$key];
            }
        }

        return null;
    }

    private function rowValue(object $row, array $candidates)
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

    private function toStringOrNull($value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function toInt($value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function result(
        bool $found,
        ?int $code,
        ?string $description,
        bool $requiresMaintenancePayment,
        ?string $error
    ): array {
        return [
            'found' => $found,
            'forma_pagamento_codice' => $code,
            'forma_pagamento_descrizione' => $description,
            'richiede_pagamento_manutentore' => $requiresMaintenancePayment,
            'error' => $error,
        ];
    }
}

