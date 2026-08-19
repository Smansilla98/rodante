<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->index('accumulated_km');
            $table->index('current_tread_min');
        });

        Schema::table('tire_assignment_segments', function (Blueprint $table) {
            $table->index(['tire_assignment_id', 'ended_at']);
        });

        Schema::table('tire_measurements', function (Blueprint $table) {
            $table->index(['tire_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropIndex(['accumulated_km']);
            $table->dropIndex(['current_tread_min']);
        });

        Schema::table('tire_assignment_segments', function (Blueprint $table) {
            $table->dropIndex(['tire_assignment_id', 'ended_at']);
        });

        Schema::table('tire_measurements', function (Blueprint $table) {
            $table->dropIndex(['tire_id', 'measured_at']);
        });
    }
};
