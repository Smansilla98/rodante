<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->index(['company_id', 'status', 'individual_number'], 'tires_company_status_number_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tires', function (Blueprint $table) {
            $table->dropIndex('tires_company_status_number_idx');
        });
    }
};
