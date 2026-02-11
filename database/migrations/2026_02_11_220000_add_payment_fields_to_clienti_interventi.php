<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clienti', function (Blueprint $table) {
            if (!Schema::hasColumn('clienti', 'forma_pagamento_codice')) {
                $table->unsignedInteger('forma_pagamento_codice')->nullable()->after('codice_esterno');
            }
            if (!Schema::hasColumn('clienti', 'forma_pagamento_descrizione')) {
                $table->string('forma_pagamento_descrizione', 255)->nullable()->after('forma_pagamento_codice');
            }
            if (!Schema::hasColumn('clienti', 'richiede_pagamento_manutentore')) {
                $table->boolean('richiede_pagamento_manutentore')->default(false)->after('forma_pagamento_descrizione');
            }
        });

        Schema::table('interventi', function (Blueprint $table) {
            if (!Schema::hasColumn('interventi', 'pagamento_metodo')) {
                $table->string('pagamento_metodo', 20)->nullable()->after('note');
            }
            if (!Schema::hasColumn('interventi', 'pagamento_importo')) {
                $table->decimal('pagamento_importo', 10, 2)->nullable()->after('pagamento_metodo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interventi', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('interventi', 'pagamento_metodo')) {
                $drop[] = 'pagamento_metodo';
            }
            if (Schema::hasColumn('interventi', 'pagamento_importo')) {
                $drop[] = 'pagamento_importo';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('clienti', function (Blueprint $table) {
            $drop = [];
            if (Schema::hasColumn('clienti', 'forma_pagamento_codice')) {
                $drop[] = 'forma_pagamento_codice';
            }
            if (Schema::hasColumn('clienti', 'forma_pagamento_descrizione')) {
                $drop[] = 'forma_pagamento_descrizione';
            }
            if (Schema::hasColumn('clienti', 'richiede_pagamento_manutentore')) {
                $drop[] = 'richiede_pagamento_manutentore';
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};

