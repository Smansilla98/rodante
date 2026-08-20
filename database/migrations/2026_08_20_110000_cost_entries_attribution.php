<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_entries', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->nullable()->after('amount');
            $table->unsignedInteger('quantity')->default(1)->after('unit_price');
            $table->foreignId('fleet_unit_id')->nullable()->after('tire_id')->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('unit_position_id')->nullable()->after('fleet_unit_id')->constrained('unit_positions')->nullOnDelete();
            $table->index(['company_id', 'fleet_unit_id']);
            $table->index(['company_id', 'unit_position_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cost_entries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'fleet_unit_id']);
            $table->dropIndex(['company_id', 'unit_position_id']);
            $table->dropConstrainedForeignId('fleet_unit_id');
            $table->dropConstrainedForeignId('unit_position_id');
            $table->dropColumn(['unit_price', 'quantity']);
        });
    }
};
