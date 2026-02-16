<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('anomalie')) {
            return;
        }

        DB::table('anomalie')->updateOrInsert(
            [
                'categoria' => 'Idrante',
                'etichetta' => 'Lastra rotta o mancante',
            ],
            [
                'attiva' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('anomalie')) {
            return;
        }

        DB::table('anomalie')
            ->where('categoria', 'Idrante')
            ->where('etichetta', 'Lastra rotta o mancante')
            ->delete();
    }
};

