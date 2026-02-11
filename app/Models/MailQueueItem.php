<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailQueueItem extends Model
{
    protected $table = 'mail_queue_items';

    protected $fillable = [
        'intervento_id',
        'tipo',
        'to_email',
        'subject',
        'body',
        'payload',
        'send_after',
        'status',
        'attempts',
        'sent_at',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'send_after' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function intervento(): BelongsTo
    {
        return $this->belongsTo(Intervento::class);
    }
}
