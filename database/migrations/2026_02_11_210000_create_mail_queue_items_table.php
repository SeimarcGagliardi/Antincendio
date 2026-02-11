<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_queue_items')) {
            return;
        }

        Schema::create('mail_queue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intervento_id')->nullable()->constrained('interventi')->nullOnDelete();
            $table->string('tipo', 60)->index();
            $table->string('to_email', 255);
            $table->string('subject', 255);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('send_after')->index();
            $table->string('status', 30)->default('queued')->index(); // queued|processing|sent|failed
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->dateTime('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_queue_items');
    }
};
