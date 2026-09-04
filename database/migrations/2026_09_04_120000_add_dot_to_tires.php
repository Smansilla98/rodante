<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->string('dot', 20)->nullable()->after('individual_number');
            $table->unique(['company_id', 'dot']);
        });

        Schema::table('tire_purchase_items', function (Blueprint $table) {
            $table->string('dot', 20)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('tire_purchase_items', function (Blueprint $table) {
            $table->dropColumn('dot');
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'dot']);
            $table->dropColumn('dot');
        });
    }
};
