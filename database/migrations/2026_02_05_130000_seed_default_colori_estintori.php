<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $colori = [
            'Rosso chiaro' => '#FCA5A5',
            'Rosso scuro'  => '#B91C1C',
            'Giallo scuro' => '#A16207',
            'Fucsia'       => '#D946EF',
            'Arancione'    => '#F97316',
            'Nero'         => '#111827',
            'Verde scuro'  => '#15803D',
            'Verde chiaro' => '#4ADE80',
            'Blu royale'   => '#4169E1',
            'Viola'        => '#7C3AED',
            'Azzurro'      => '#38BDF8',
            'Marrone'      => '#8B5E3C',
        ];

        $colorIds = [];
        foreach ($colori as $nome => $hex) {
            $id = DB::table('colori')->where('nome', $nome)->value('id');
            if ($id) {
                DB::table('colori')->where('id', $id)->update([
                    'hex' => strtoupper($hex),
                    'updated_at' => $now,
                ]);
            } else {
                $id = DB::table('colori')->insertGetId([
                    'nome' => $nome,
                    'hex' => strtoupper($hex),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $colorIds[$nome] = $id;
        }

        $tipi = DB::table('tipi_estintori')
            ->select('id','sigla','descrizione','kg','tipo','colore_id')
            ->get();

        foreach ($tipi as $tipo) {
            if (!empty($tipo->colore_id)) continue;

            $sigla = strtoupper(trim((string) $tipo->sigla));
            $text = strtoupper(trim(
                ($tipo->sigla ?? '').' '.($tipo->descrizione ?? '').' '.($tipo->tipo ?? '')
            ));
            $kg = (int) ($tipo->kg ?? 0);
            if ($kg <= 0) {
                $kg = $this->extractCapacity($text) ?? 0;
            }
            $agent = $this->detectAgent($text);

            $colorName = null;
            if ($sigla === 'ESC027') $colorName = 'Giallo scuro';
            elseif ($sigla === 'ESP001') $colorName = 'Fucsia';
            elseif ($sigla === 'ESP002') $colorName = 'Arancione';
            elseif ($sigla === 'ESP009') $colorName = 'Verde scuro';
            elseif ($agent === 'CO2' && $kg === 2) $colorName = 'Rosso chiaro';
            elseif ($agent === 'CO2' && $kg === 5) $colorName = 'Rosso scuro';
            elseif ($agent === 'CO2' && $kg === 27) $colorName = 'Giallo scuro';
            elseif ($agent === 'POLVERE' && $kg === 6) $colorName = 'Nero';
            elseif ($agent === 'POLVERE' && $kg === 12) $colorName = 'Verde chiaro';
            elseif ($agent === 'POLVERE' && $kg === 30) $colorName = 'Viola';
            elseif ($agent === 'POLVERE' && $kg === 50) $colorName = 'Blu royale';
            elseif ($agent === 'SCHIUMA' && $kg === 6) $colorName = 'Azzurro';
            elseif ($agent === 'SCHIUMA') $colorName = 'Marrone';

            if ($colorName && isset($colorIds[$colorName])) {
                DB::table('tipi_estintori')
                    ->where('id', $tipo->id)
                    ->update(['colore_id' => $colorIds[$colorName]]);
            }
        }
    }

    public function down(): void
    {
        // Non rimuoviamo i colori per evitare perdita dati.
    }

    private function detectAgent(string $txt): ?string
    {
        if (preg_match('/\bCO\\s*2\\b|\bCO2\\b|ANIDRIDE\\s+CARBONICA/', $txt)) return 'CO2';
        if (preg_match('/POLV|POLVER/', $txt)) return 'POLVERE';
        if (preg_match('/SCHIUMA|AFFF|FOAM/', $txt)) return 'SCHIUMA';
        return null;
    }

    private function extractCapacity(string $txt): ?int
    {
        if (preg_match('/\b(\d{1,3})(?:[,.]\d+)?\s*(KG|KGS|KG\.|LT|L|LT\.)\b/u', $txt, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(KG|KGS|KG\.|LT|L|LT\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $txt, $m)) {
            return (int) $m[2];
        }
        return null;
    }
};
