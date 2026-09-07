<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_couplings', function (Blueprint $table) {
            $table->dropUnique(['open_tractor_key']);
            $table->unsignedTinyInteger('slot_order')->default(1)->after('trailer_id');
            $table->index(['tractor_id', 'uncoupled_at', 'slot_order'], 'unit_couplings_tractor_open_order_idx');
        });

        Schema::table('fleet_units', function (Blueprint $table) {
            $table->string('duty', 40)->nullable()->after('status');
            $table->index('duty');
        });

        Schema::table('tire_models', function (Blueprint $table) {
            $table->boolean('winter_capable')->default(false)->after('application');
        });

        Schema::table('tire_operations', function (Blueprint $table) {
            $table->boolean('odometer_provisional')->default(false)->after('odometer');
        });
    }

    public function down(): void
    {
        Schema::table('tire_operations', function (Blueprint $table) {
            $table->dropColumn('odometer_provisional');
        });

        Schema::table('tire_models', function (Blueprint $table) {
            $table->dropColumn('winter_capable');
        });

        Schema::table('fleet_units', function (Blueprint $table) {
            $table->dropIndex(['duty']);
            $table->dropColumn('duty');
        });

        Schema::table('unit_couplings', function (Blueprint $table) {
            $table->dropIndex('unit_couplings_tractor_open_order_idx');
            $table->dropColumn('slot_order');
            $table->unique('open_tractor_key');
        });
    }
};
