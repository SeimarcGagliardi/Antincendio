<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('presidi', 'idrante_tipo')) {
                $table->string('idrante_tipo', 20)->nullable()->after('tipo_estintore_id');
                $table->string('idrante_lunghezza', 20)->nullable()->after('idrante_tipo');
                $table->boolean('idrante_sopra_suolo')->default(false)->after('idrante_lunghezza');
                $table->boolean('idrante_sotto_suolo')->default(false)->after('idrante_sopra_suolo');
                $table->string('porta_tipo', 50)->nullable()->after('idrante_sotto_suolo');
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('import_presidi', 'idrante_tipo')) {
                $table->string('idrante_tipo', 20)->nullable()->after('tipo_estintore_id');
                $table->string('idrante_lunghezza', 20)->nullable()->after('idrante_tipo');
                $table->boolean('idrante_sopra_suolo')->default(false)->after('idrante_lunghezza');
                $table->boolean('idrante_sotto_suolo')->default(false)->after('idrante_sopra_suolo');
                $table->string('porta_tipo', 50)->nullable()->after('idrante_sotto_suolo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (Schema::hasColumn('presidi', 'idrante_tipo')) {
                $table->dropColumn([
                    'idrante_tipo',
                    'idrante_lunghezza',
                    'idrante_sopra_suolo',
                    'idrante_sotto_suolo',
                    'porta_tipo',
                ]);
            }
        });

        Schema::table('import_presidi', function (Blueprint $table) {
            if (Schema::hasColumn('import_presidi', 'idrante_tipo')) {
                $table->dropColumn([
                    'idrante_tipo',
                    'idrante_lunghezza',
                    'idrante_sopra_suolo',
                    'idrante_sotto_suolo',
                    'porta_tipo',
                ]);
            }
        });
    }
};
