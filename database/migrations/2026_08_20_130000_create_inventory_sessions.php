<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->string('status');
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('found_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->unsignedInteger('unexpected_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('counting_started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('adjustments_applied')->default(false);
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'base_id', 'status']);
        });

        Schema::create('inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->string('expected_kind')->nullable();
            $table->foreignId('expected_base_id')->nullable()->constrained('bases')->nullOnDelete();
            $table->foreignId('expected_unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->boolean('in_snapshot')->default(true);
            $table->boolean('found')->default(false);
            $table->string('delta')->nullable();
            $table->string('observed_kind')->nullable();
            $table->foreignId('observed_base_id')->nullable()->constrained('bases')->nullOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('adjustment_applied')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['inventory_session_id', 'tire_id']);
            $table->index(['inventory_session_id', 'delta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_lines');
        Schema::dropIfExists('inventory_sessions');
    }
};
