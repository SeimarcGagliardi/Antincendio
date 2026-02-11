<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('presidio_intervento_anomalie')) {
            return;
        }

        Schema::create('presidio_intervento_anomalie', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('presidio_intervento_id');
            $table->unsignedBigInteger('anomalia_id');
            $table->boolean('riparata')->default(false);
            $table->timestamps();

            $table->unique(['presidio_intervento_id', 'anomalia_id'], 'pia_unique_presidio_anomalia');
            $table->index('anomalia_id');

            $table->foreign('presidio_intervento_id', 'pia_fk_presidio_intervento')
                ->references('id')
                ->on('presidi_intervento')
                ->onDelete('cascade');

            $table->foreign('anomalia_id', 'pia_fk_anomalia')
                ->references('id')
                ->on('anomalie')
                ->onDelete('cascade');
        });

        DB::table('presidi_intervento')
            ->select(['id', 'anomalie'])
            ->whereNotNull('anomalie')
            ->orderBy('id')
            ->chunkById(250, function ($rows) {
                $now = now();
                foreach ($rows as $row) {
                    $decoded = json_decode((string) $row->anomalie, true);
                    if (!is_array($decoded) || empty($decoded)) {
                        continue;
                    }
                    $ids = collect($decoded)
                        ->filter(fn ($id) => is_numeric($id))
                        ->map(fn ($id) => (int) $id)
                        ->unique()
                        ->values()
                        ->all();

                    foreach ($ids as $anomaliaId) {
                        DB::table('presidio_intervento_anomalie')->updateOrInsert(
                            [
                                'presidio_intervento_id' => (int) $row->id,
                                'anomalia_id' => $anomaliaId,
                            ],
                            [
                                'riparata' => false,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('presidio_intervento_anomalie');
    }
};
