<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Observaciones libres de la ficha del neumático (sección "Modificar neumático").
     * Nullable y aditiva: no toca ninguna fila existente.
     */
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('retired_at');
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
