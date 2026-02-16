<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (!Schema::hasColumn('presidi', 'data_eliminazione')) {
                $table->dateTime('data_eliminazione')->nullable()->after('updated_at');
                $table->index('data_eliminazione', 'idx_presidi_data_eliminazione');
            }
        });
    }

    public function down(): void
    {
        Schema::table('presidi', function (Blueprint $table) {
            if (Schema::hasColumn('presidi', 'data_eliminazione')) {
                $table->dropIndex('idx_presidi_data_eliminazione');
                $table->dropColumn('data_eliminazione');
            }
        });
    }
};
