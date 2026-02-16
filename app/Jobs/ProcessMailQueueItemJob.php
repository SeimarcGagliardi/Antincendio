<?php

namespace App\Jobs;

use App\Services\Interventi\MailQueueProcessorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessMailQueueItemJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $mailQueueItemId;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(int $mailQueueItemId)
    {
        $this->mailQueueItemId = $mailQueueItemId;
    }

    public function handle(MailQueueProcessorService $processor): void
    {
        $processor->processById($this->mailQueueItemId);
    }
}

