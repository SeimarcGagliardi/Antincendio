<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anomalie')) {
            Schema::table('anomalie', function (Blueprint $table) {
                if (!Schema::hasColumn('anomalie', 'usa_prezzi_tipo_estintore')) {
                    $table->boolean('usa_prezzi_tipo_estintore')->default(false)->after('prezzo');
                }
                if (!Schema::hasColumn('anomalie', 'usa_prezzi_tipo_presidio')) {
                    $table->boolean('usa_prezzi_tipo_presidio')->default(false)->after('usa_prezzi_tipo_estintore');
                }
            });
        }

        if (!Schema::hasTable('anomalia_prezzi_tipo_estintore')) {
            Schema::create('anomalia_prezzi_tipo_estintore', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('anomalia_id');
                $table->unsignedBigInteger('tipo_estintore_id');
                $table->decimal('prezzo', 10, 2)->default(0);
                $table->timestamps();

                $table->unique(
                    ['anomalia_id', 'tipo_estintore_id'],
                    'uq_anomalia_tipo_estintore'
                );
                $table->index('tipo_estintore_id', 'idx_anomalia_tipo_estintore_tipo');

                $table->foreign('anomalia_id', 'fk_anomalia_tipo_estintore_anomalia')
                    ->references('id')
                    ->on('anomalie')
                    ->onDelete('cascade');

                $table->foreign('tipo_estintore_id', 'fk_anomalia_tipo_estintore_tipo')
                    ->references('id')
                    ->on('tipi_estintori')
                    ->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('anomalia_prezzi_tipo_presidio')) {
            Schema::create('anomalia_prezzi_tipo_presidio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('anomalia_id');
                $table->unsignedBigInteger('tipo_presidio_id');
                $table->decimal('prezzo', 10, 2)->default(0);
                $table->timestamps();

                $table->unique(
                    ['anomalia_id', 'tipo_presidio_id'],
                    'uq_anomalia_tipo_presidio'
                );
                $table->index('tipo_presidio_id', 'idx_anomalia_tipo_presidio_tipo');

                $table->foreign('anomalia_id', 'fk_anomalia_tipo_presidio_anomalia')
                    ->references('id')
                    ->on('anomalie')
                    ->onDelete('cascade');

                $table->foreign('tipo_presidio_id', 'fk_anomalia_tipo_presidio_tipo')
                    ->references('id')
                    ->on('tipi_presidio')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalia_prezzi_tipo_presidio');
        Schema::dropIfExists('anomalia_prezzi_tipo_estintore');

        if (Schema::hasTable('anomalie')) {
            Schema::table('anomalie', function (Blueprint $table) {
                $drop = [];
                if (Schema::hasColumn('anomalie', 'usa_prezzi_tipo_presidio')) {
                    $drop[] = 'usa_prezzi_tipo_presidio';
                }
                if (Schema::hasColumn('anomalie', 'usa_prezzi_tipo_estintore')) {
                    $drop[] = 'usa_prezzi_tipo_estintore';
                }

                if (!empty($drop)) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};

