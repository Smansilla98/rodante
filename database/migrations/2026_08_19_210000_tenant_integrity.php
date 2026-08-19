<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropUnique(['individual_number']);
            $table->unique(['company_id', 'individual_number']);
        });

        Schema::table('tire_purchases', function (Blueprint $table) {
            $table->dropUnique(['number']);
            $table->unique(['company_id', 'number']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('open_tire_id')->nullable()->unique();
        });

        DB::table('work_orders')
            ->whereIn('status', ['ABIERTA', 'EN_TALLER'])
            ->update(['open_tire_id' => DB::raw('tire_id')]);
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropUnique(['open_tire_id']);
            $table->dropColumn('open_tire_id');
        });
        Schema::table('tire_purchases', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'number']);
            $table->unique('number');
        });
        Schema::table('tires', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'individual_number']);
            $table->unique('individual_number');
        });
    }
};
