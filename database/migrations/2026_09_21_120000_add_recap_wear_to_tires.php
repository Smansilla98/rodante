<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clasificación manual de desgaste para cubiertas recapadas ("Recapada Nueva" vs
     * "Recapada Usada") — a diferencia de `condition` (Nueva→Usada), esto NO se calcula
     * solo por km: el usuario lo marca a mano (ver Tire::displayCondition()).
     * Nullable y aditiva: no toca ninguna fila existente.
     */
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->string('recap_wear', 20)->nullable()->after('condition');
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropColumn('recap_wear');
        });
    }
};
