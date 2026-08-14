<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('unit_configurations') && ! Schema::hasColumn('unit_configurations', 'compatible_types')) {
            Schema::table('unit_configurations', function (Blueprint $table) {
                $table->json('compatible_types')->nullable()->after('applies_to');
                $table->text('description')->nullable()->after('compatible_types');
                $table->unsignedTinyInteger('drive_axle_count')->default(0)->after('axle_count');
            });
        }

        if (Schema::hasTable('unit_positions') && ! Schema::hasColumn('unit_positions', 'axle_role')) {
            Schema::table('unit_positions', function (Blueprint $table) {
                $table->string('axle_role')->default('ARRASTRE')->after('axle_number');
                $table->boolean('is_liftable')->default(false)->after('is_spare');
                $table->boolean('is_self_steer')->default(false)->after('is_liftable');
            });
        }

        if (Schema::hasTable('fleet_units') && ! Schema::hasColumn('fleet_units', 'specs')) {
            Schema::table('fleet_units', function (Blueprint $table) {
                $table->json('specs')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('unit_configurations', 'compatible_types')) {
            Schema::table('unit_configurations', function (Blueprint $table) {
                $table->dropColumn(['compatible_types', 'description', 'drive_axle_count']);
            });
        }

        if (Schema::hasColumn('unit_positions', 'axle_role')) {
            Schema::table('unit_positions', function (Blueprint $table) {
                $table->dropColumn(['axle_role', 'is_liftable', 'is_self_steer']);
            });
        }

        if (Schema::hasColumn('fleet_units', 'specs')) {
            Schema::table('fleet_units', function (Blueprint $table) {
                $table->dropColumn('specs');
            });
        }
    }
};
