<?php

namespace App\Console\Commands;

use App\Models\MailQueueItem;
use App\Services\Interventi\MailQueueProcessorService;
use Illuminate\Console\Command;

class ProcessMailQueue extends Command
{
    protected $signature = 'mail-queue:process {--limit=25 : Numero massimo di email da processare per esecuzione}';
    protected $description = 'Processa la coda email interna dei rapportini intervento';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $items = MailQueueItem::query()
            ->where('status', 'queued')
            ->where('send_after', '<=', now())
            ->orderBy('send_after')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->line('Nessuna email in coda da processare.');
            return self::SUCCESS;
        }

        $processor = app(MailQueueProcessorService::class);
        $processed = 0;

        foreach ($items as $item) {
            if ($processor->processById((int) $item->id)) {
                $processed++;
            }
        }

        $this->info("Processate {$processed} email di coda.");
        return self::SUCCESS;
    }
}
