<?php

namespace App\Services\Presidi;

use App\Models\Cliente;
use App\Models\Presidio;
use App\Models\Sede;
use App\Models\TipoEstintore;
use App\Livewire\Presidi\ImportaPresidi;
use Illuminate\Support\Carbon;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;

class DocxPresidiImporter
{
    private int $clienteId;
    private ?int $sedeId;
    private array $mesiPreferiti = [];
    private ?\Illuminate\Support\Collection $tipiCache = null;

    public function __construct(int $clienteId, ?int $sedeId = null)
    {
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;
        $this->mesiPreferiti = $this->caricaMesiPreferiti();
    }

    public function importFromPath(string $path): array
    {
        $word = IOFactory::load($path);
        $importati = 0;
        $saltati = 0;

        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if (!$element instanceof Table) continue;

                $headersMap = null;

                foreach ($element->getRows() as $row) {
                    $cells = $row->getCells();
                    if (!count($cells)) continue;

                    $vals = array_map(fn($c) => self::cellText($c), $cells);

                    if ($headersMap === null) {
                        $score = 0;
                        foreach ($vals as $v) {
                            $hv = self::normalizzaHeader($v);
                            if (in_array($hv, [
                                'numero','ubicazione','tipo_contratto','kglt','classe',
                                'anno_acquisto','scadenza_presidio','anno_serbatoio',
                                'riempimento_revisione','collaudo_revisione'
                            ], true)) {
                                $score++;
                            }
                        }
                        if ($score >= 3) {
                            $headersMap = [];
                            foreach ($vals as $i => $v) $headersMap[$i] = self::normalizzaHeader($v);
                            continue;
                        }
                    }
                    if ($headersMap === null) continue;

                    $r = [];
                    foreach ($vals as $i => $v) {
                        $k = $headersMap[$i] ?? "col_$i";
                        $r[$k] = $v;
                    }

                    $numero = $r['numero'] ?? null;
                    if (!is_numeric($numero)) continue;

                    $ubic      = $r['ubicazione'] ?? '';
                    $contratto = $r['tipo_contratto'] ?? '';

                    $tipoRaw  = trim((($r['kglt'] ?? '') . ' ' . ($r['classe'] ?? '')));
                    $joinedUp = mb_strtoupper(implode(' ', $vals));

                    $tipoEstId = $this->guessTipoEstintoreId($tipoRaw !== '' ? $tipoRaw : $joinedUp);
                    $tipoEst   = $tipoEstId ? TipoEstintore::with('classificazione')->find($tipoEstId) : null;
                    $classi    = $tipoEst?->classificazione;

                    $dataAcquisto      = self::parseData($r['anno_acquisto'] ?? null);
                    $scadPresidio      = self::parseData($r['scadenza_presidio'] ?? null);
                    $dataSerbatoioRaw  = $r['anno_serbatoio'] ?? null;
                    $dataSerb          = self::parseData($dataSerbatoioRaw);
                    $marcaSerbatoio    = ImportaPresidi::parseMarcaSerbatoio($dataSerbatoioRaw);
                    $dataUltimaRev     = ImportaPresidi::parseDataCell($r['riempimento_revisione'] ?? null);
                    if ($dataUltimaRev && Carbon::parse($dataUltimaRev)->startOfDay()->gt(now()->startOfDay())) {
                        $dataUltimaRev = null;
                    }

                    if (!$dataSerb || !$tipoEstId) {
                        $saltati++;
                        continue;
                    }

                    $periodoRev    = ImportaPresidi::pickPeriodoRevisione($dataSerb, $classi, $dataUltimaRev, $marcaSerbatoio);
                    $baseRevisione = $dataUltimaRev ?: $dataSerb;
                    $scadRevisione = ImportaPresidi::nextDueAfter($baseRevisione, $periodoRev);
                    $scadCollaudo  = !empty($classi?->anni_collaudo)
                        ? ImportaPresidi::nextDueAfter($dataSerb, (int)$classi->anni_collaudo)
                        : null;
                    $fineVita      = ImportaPresidi::addYears($dataSerb, $classi?->anni_fine_vita);

                    $revAligned  = self::visitaOnOrBeforeWithMonths($scadRevisione, $this->mesiPreferiti);
                    $colAligned  = self::visitaOnOrBeforeWithMonths($scadCollaudo, $this->mesiPreferiti);
                    $fineAligned = self::visitaOnOrBeforeWithMonths($fineVita, $this->mesiPreferiti);

                    $scadenzaAssoluta = ImportaPresidi::minDate($scadRevisione, $scadCollaudo, $fineVita, $scadPresidio);
                    $dataSostituzione = self::visitaOnOrBeforeWithMonths($scadenzaAssoluta, $this->mesiPreferiti);

                    $flag1 = str_contains($joinedUp, 'CARTELLO');
                    $flag2 = str_contains($joinedUp, 'TERRA');
                    $flag3 = str_contains($joinedUp, 'NUMERAZ');

                    $sedeId = $this->resolveSedeId();

                    Presidio::updateOrCreate(
                        [
                            'cliente_id' => $this->clienteId,
                            'sede_id'    => $sedeId,
                            'categoria'  => 'Estintore',
                            'progressivo'=> (int)$numero,
                        ],
                        [
                            'ubicazione'           => $ubic,
                            'tipo_contratto'       => $contratto,
                            'tipo_estintore'       => $tipoRaw,
                            'tipo_estintore_id'    => $tipoEstId,
                            'flag_anomalia1'       => $flag1,
                            'flag_anomalia2'       => $flag2,
                            'flag_anomalia3'       => $flag3,
                            'note'                 => null,
                            'data_acquisto'        => $dataAcquisto,
                            'scadenza_presidio'    => $scadPresidio,
                            'data_serbatoio'       => $dataSerb,
                            'marca_serbatoio'      => $marcaSerbatoio,
                            'data_ultima_revisione'=> $dataUltimaRev,
                            'data_revisione'       => $revAligned ?? $scadRevisione,
                            'data_collaudo'        => $colAligned ?? $scadCollaudo,
                            'data_fine_vita'       => $fineAligned ?? $fineVita,
                            'data_sostituzione'    => $dataSostituzione,
                        ]
                    );

                    $importati++;
                }
            }
        }

        return ['importati' => $importati, 'saltati' => $saltati];
    }

    private function resolveSedeId(): int
    {
        if ($this->sedeId) return $this->sedeId;

        $sede = Sede::where('cliente_id', $this->clienteId)->first();
        if ($sede) return $sede->id;

        $cliente = Cliente::find($this->clienteId);
        $new = Sede::create([
            'cliente_id' => $this->clienteId,
            'nome'       => 'Sede principale',
            'indirizzo'  => $cliente?->indirizzo,
            'citta'      => $cliente?->citta,
            'cap'        => $cliente?->cap,
            'provincia'  => $cliente?->provincia,
            'codice_esterno' => $cliente?->codice_esterno,
            'minuti_intervento' => $cliente?->minuti_intervento ?? 60,
            'mesi_visita' => $cliente?->mesi_visita ?? [],
        ]);
        return $new->id;
    }

    private function caricaMesiPreferiti(): array
    {
        $cliente = Cliente::find($this->clienteId);
        $sede    = $this->sedeId ? Sede::find($this->sedeId) : null;

        $candidates = [
            $sede?->mesi_visita ?? null,
            $cliente?->mesi_visita ?? null,
            $cliente?->mesi_intervento ?? null,
            $cliente?->mesi ?? null,
        ];
        foreach ($candidates as $raw) {
            $arr = self::normalizeMonths($raw);
            if (!empty($arr)) return $arr;
        }
        return [];
    }

    private static function normalizeMonths($raw): array
    {
        if (!$raw) return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = array_map('trim', explode(',', $raw));
            }
        }
        if (is_array($raw)) {
            return collect($raw)
                ->map(fn($m)=>(int)$m)
                ->filter(fn($m)=>$m>=1 && $m<=12)
                ->unique()->sort()->values()->all();
        }
        return [];
    }

    private static function visitaOnOrBeforeWithMonths(?string $due, array $months): ?string
    {
        if (!$due) return null;
        $dueC = Carbon::parse($due)->startOfMonth();
        if (!count($months)) return $dueC->format('Y-m-d');
        if (in_array((int)$dueC->month, $months, true)) return $dueC->format('Y-m-d');

        sort($months);
        $year = $dueC->year;
        $m = $dueC->month;
        $before = array_values(array_filter($months, fn($vm) => $vm < $m));
        if (!empty($before)) {
            $month = end($before);
        } else {
            $month = end($months);
            $year -= 1;
        }
        return Carbon::create($year, $month, 1)->format('Y-m-d');
    }

    private static function parseData(?string $txt): ?string
    {
        $txt = trim((string)$txt);
        if ($txt === '') return null;
        if (preg_match('/\b(\d{1,2})\s*[\.\/-]\s*(\d{4})\b/', $txt, $m)) {
            $mese = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return Carbon::createFromFormat('Y-m-d', "{$m[2]}-{$mese}-01")->format('Y-m-d');
        }
        if (preg_match('/\b(\d{4})\b/', $txt, $y)) {
            return Carbon::createFromDate((int)$y[1], 1, 1)->format('Y-m-d');
        }
        return null;
    }

    private static function cellText($cell): string
    {
        $txt = '';
        foreach ($cell->getElements() as $e) {
            if ($e instanceof Text) $txt .= $e->getText();
            elseif ($e instanceof TextRun) {
                foreach ($e->getElements() as $t)
                    if ($t instanceof Text) $txt .= $t->getText();
            }
        }
        return trim($txt);
    }

    private static function normalizzaHeader(string $h): string
    {
        $h = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $h)));
        $map = [
            'N'                          => 'numero',
            'N.'                         => 'numero',
            'UBICAZIONE'                 => 'ubicazione',
            'TIPO CONTRATTO'             => 'tipo_contratto',
            'KG/LT'                      => 'kglt',
            'CLASSE ESTINGUENTE'         => 'classe',
            'ANNO ACQUISTO FULL'         => 'anno_acquisto',
            'SCADENZA PRESIDIO FULL'     => 'scadenza_presidio',
            'ANNO SERBATOIO'             => 'anno_serbatoio',
            'RIEMPIMENTO/ REVISIONE'     => 'riempimento_revisione',
            'RIEMPIMENTO/REVISIONE'      => 'riempimento_revisione',
            'COLLAUDO/ REVISIONE'        => 'collaudo_revisione',
            'COLLAUDO/REVISIONE'         => 'collaudo_revisione',
        ];
        return $map[$h] ?? $h;
    }

    private function loadTipiCache(): void
    {
        if ($this->tipiCache) return;
        $this->tipiCache = TipoEstintore::query()
            ->select('id','descrizione','sigla','kg')
            ->orderBy('kg')->orderBy('id')
            ->get()
            ->map(function ($t) {
                $txt = strtoupper($t->descrizione.' '.$t->sigla);
                return [
                    'id'          => $t->id,
                    'kg'          => (int) $t->kg,
                    'descrizione' => $t->descrizione,
                    'sigla'       => $t->sigla,
                    'agente'      => $this->detectAgent($txt),
                    'full'        => $txt,
                ];
            });
    }

    private function detectAgent(string $txt): ?string
    {
        $u = strtoupper($txt);
        if (preg_match('/\bCO\s*2\b|\bCO2\b|ANIDRIDE\s+CARBONICA/', $u)) return 'CO2';
        if (preg_match('/POLV|POLVER/', $u)) return 'POLVERE';
        if (preg_match('/SCHI|FOAM|AFFF/', $u)) return 'SCHIUMA';
        return null;
    }

    private function detectCapacity(string $txt): ?int
    {
        $u = strtoupper($txt);
        if (preg_match('/\b(\d{1,3})(?:[,.]\d+)?\s*(KG|KGS|KG\.|LT|L|LT\.)\b/u', $u, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b(KG|KGS|KG\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
            return (int) $m[2];
        }
        if (preg_match('/\b(LT|L|LT\.)\s*(\d{1,3})(?:[,.]\d+)?\b/u', $u, $m)) {
            return (int) $m[2];
        }
        return null;
    }

    private function guessTipoEstintoreId(string $raw): ?int
    {
        $this->loadTipiCache();
        $u   = strtoupper($raw);
        $kg  = $this->detectCapacity($u);
        $ag  = $this->detectAgent($u);

        $cand = $this->tipiCache
            ->when($ag, fn($c) => $c->where('agente', $ag))
            ->when($kg, fn($c) => $c->where('kg', $kg));

        if ($cand->count() === 1) return $cand->first()['id'];
        if ($cand->count() > 1) {
            $best = $cand->firstWhere('full', fn($f) => str_contains($f, (string)$kg));
            return $best['id'] ?? $cand->first()['id'];
        }

        if ($ag) {
            $cand = $this->tipiCache->where('agente', $ag);
            if ($cand->count()) return $cand->first()['id'];
        }

        if ($kg) {
            $cand = $this->tipiCache->where('kg', $kg);
            if ($cand->count()) return $cand->first()['id'];
        }

        return null;
    }
}
