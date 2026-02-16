<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anomalie', function (Blueprint $table) {
            if (!Schema::hasColumn('anomalie', 'prezzo')) {
                $table->decimal('prezzo', 10, 2)->default(0)->after('etichetta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('anomalie', function (Blueprint $table) {
            if (Schema::hasColumn('anomalie', 'prezzo')) {
                $table->dropColumn('prezzo');
            }
        });
    }
};
