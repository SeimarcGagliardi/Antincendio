<?php

namespace App\Services\Interventi;

use App\Models\MailQueueItem;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailQueueProcessorService
{
    public function processById(int $id): bool
    {
        $item = MailQueueItem::query()->find($id);
        if (!$item) {
            return false;
        }

        if (!in_array($item->status, ['queued', 'failed'], true)) {
            return false;
        }

        if ($item->send_after && $item->send_after->isFuture()) {
            return false;
        }

        $claimed = MailQueueItem::query()
            ->whereKey($id)
            ->whereIn('status', ['queued', 'failed'])
            ->update([
                'status' => 'processing',
                'updated_at' => now(),
            ]);

        if ($claimed === 0) {
            return false;
        }

        $item = MailQueueItem::query()->find($id);
        if (!$item) {
            return false;
        }

        try {
            if ($item->tipo !== 'rapportino_interno') {
                throw new \RuntimeException("Tipo mail non supportato: {$item->tipo}");
            }

            if (!$item->intervento_id) {
                throw new \RuntimeException('Intervento non valorizzato nella coda email.');
            }

            $service = app(RapportinoInterventoService::class);
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

            return true;
        } catch (Throwable $e) {
            $attempts = (int) $item->attempts + 1;
            $item->attempts = $attempts;
            if ($attempts < 5) {
                $item->status = 'queued';
                $item->send_after = now()->addMinutes(min(30, 2 * $attempts));
            } else {
                $item->status = 'failed';
            }
            $item->last_error = mb_substr($e->getMessage(), 0, 65000);
            $item->save();

            report($e);

            return false;
        }
    }
}
