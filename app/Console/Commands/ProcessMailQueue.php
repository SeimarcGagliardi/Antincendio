<?php

namespace App\Console\Commands;

use App\Models\MailQueueItem;
use App\Services\Interventi\RapportinoInterventoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

        $service = app(RapportinoInterventoService::class);
        $processed = 0;

        foreach ($items as $item) {
            $this->processItem($item, $service);
            $processed++;
        }

        $this->info("Processate {$processed} email di coda.");
        return self::SUCCESS;
    }

    private function processItem(MailQueueItem $item, RapportinoInterventoService $service): void
    {
        $item->status = 'processing';
        $item->save();

        try {
            if ($item->tipo !== 'rapportino_interno') {
                throw new \RuntimeException("Tipo mail non supportato: {$item->tipo}");
            }

            if (!$item->intervento_id) {
                throw new \RuntimeException('Intervento non valorizzato nella coda email.');
            }

            $data = $service->buildDataByInterventoId((int) $item->intervento_id);
            $pdfOutput = $service->renderPdfOutput(RapportinoInterventoService::KIND_INTERNO, $data);
            $filename = $service->filename(RapportinoInterventoService::KIND_INTERNO, $data['intervento']);

            Mail::raw(
                (string) ($item->body ?: 'In allegato il rapportino interno dell\'intervento.'),
                function ($message) use ($item, $pdfOutput, $filename) {
                    $message->to($item->to_email)
                        ->subject($item->subject)
                        ->attachData($pdfOutput, $filename, ['mime' => 'application/pdf']);
                }
            );

            $item->attempts = (int) $item->attempts + 1;
            $item->status = 'sent';
            $item->sent_at = now();
            $item->last_error = null;
            $item->save();
        } catch (Throwable $e) {
            $item->attempts = (int) $item->attempts + 1;
            $item->status = 'failed';
            $item->last_error = mb_substr($e->getMessage(), 0, 65000);
            $item->save();

            report($e);
        }
    }
}
