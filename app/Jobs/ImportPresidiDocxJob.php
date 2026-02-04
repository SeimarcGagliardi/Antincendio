<?php

namespace App\Jobs;

use App\Services\Presidi\DocxPresidiImporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportPresidiDocxJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $path;
    public int $clienteId;
    public ?int $sedeId;

    public function __construct(string $path, int $clienteId, ?int $sedeId = null)
    {
        $this->path = $path;
        $this->clienteId = $clienteId;
        $this->sedeId = $sedeId;
    }

    public function handle(): void
    {
        $importer = new DocxPresidiImporter($this->clienteId, $this->sedeId);
        $res = $importer->importFromPath($this->path);
        Log::info('[IMPORT MASSIVO] Completato', [
            'cliente_id' => $this->clienteId,
            'path' => $this->path,
            'importati' => $res['importati'] ?? 0,
            'saltati' => $res['saltati'] ?? 0,
        ]);
    }
}
