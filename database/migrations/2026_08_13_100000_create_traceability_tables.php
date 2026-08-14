<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('bases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fleet_base', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->constrained()->cascadeOnDelete();
            $table->unique(['fleet_id', 'base_id']);
        });

        Schema::create('user_fleet_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'fleet_id']);
        });

        Schema::create('user_base_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'base_id']);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tire_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tire_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_brand_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tire_brand_id', 'code']);
        });

        Schema::create('tire_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('alias')->nullable();
            $table->unsignedSmallInteger('width_mm')->nullable();
            $table->unsignedSmallInteger('aspect_ratio')->nullable();
            $table->decimal('rim_inches', 4, 1)->nullable();
            $table->unsignedTinyInteger('uneven_wear_threshold_mm')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tire_model_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tire_size_id')->constrained()->cascadeOnDelete();
            $table->unique(['tire_model_id', 'tire_size_id']);
        });

        Schema::create('measurement_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_size_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unique(['tire_size_id', 'code']);
        });

        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('has_odometer')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('unit_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('family_code');
            $table->string('applies_to');
            $table->json('compatible_types')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('axle_count');
            $table->unsignedTinyInteger('drive_axle_count')->default(0);
            $table->unsignedTinyInteger('position_count');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('unit_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->unsignedTinyInteger('axle_number');
            $table->string('axle_role')->default('ARRASTRE');
            $table->string('side');
            $table->string('dual')->nullable();
            $table->boolean('is_spare')->default(false);
            $table->boolean('is_liftable')->default(false);
            $table->boolean('is_self_steer')->default(false);
            $table->unsignedTinyInteger('grid_row')->default(0);
            $table->unsignedTinyInteger('grid_col')->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->unique(['unit_configuration_id', 'code']);
        });

        Schema::create('movement_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('applies_to');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('fleet_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fleet_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_configuration_id')->constrained()->restrictOnDelete();
            $table->string('plate')->unique();
            $table->string('brand')->nullable();
            $table->string('model_name')->nullable();
            $table->unsignedInteger('current_odometer')->default(0);
            $table->string('status');
            $table->json('specs')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['fleet_id', 'base_id']);
        });

        Schema::create('tire_purchases', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('base_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('purchased_at');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tire_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_size_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('first_number')->nullable();
            $table->unsignedInteger('last_number')->nullable();
            $table->timestamps();
        });

        Schema::create('tires', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('individual_number')->unique();
            $table->foreignId('tire_brand_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_size_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_purchase_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('current_lifecycle_id')->nullable();
            $table->string('status');
            $table->string('condition');
            $table->unsignedInteger('accumulated_km')->default(0);
            $table->decimal('current_tread_min', 5, 1)->nullable();
            $table->date('purchased_at')->nullable();
            $table->date('retired_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'condition']);
            $table->index(['tire_brand_id', 'tire_model_id', 'tire_size_id']);
        });

        Schema::create('tire_lifecycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('life_number');
            $table->string('started_by');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('km_in_life')->default(0);
            $table->string('condition_at_start');
            $table->unique(['tire_id', 'life_number']);
        });

        Schema::table('tires', function (Blueprint $table) {
            $table->foreign('current_lifecycle_id')->references('id')->on('tire_lifecycles')->nullOnDelete();
        });

        Schema::create('tire_current_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->unique()->constrained()->restrictOnDelete();
            $table->string('location_kind');
            $table->foreignId('base_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('unit_positions')->nullOnDelete();
            $table->timestamps();
            $table->unique(['unit_id', 'position_id']);
            $table->index(['location_kind', 'base_id']);
        });

        Schema::create('tire_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->foreignId('odometer_unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('odometer');
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tire_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_lifecycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->foreignId('start_position_id')->constrained('unit_positions')->restrictOnDelete();
            $table->foreignId('end_position_id')->nullable()->constrained('unit_positions')->nullOnDelete();
            $table->boolean('counts_km')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('open_key')->nullable()->unique();
            $table->timestamps();
            $table->index(['tire_id', 'ended_at']);
        });

        Schema::create('tire_assignment_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('odometer_unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->unsignedInteger('start_odometer');
            $table->unsignedInteger('end_odometer')->nullable();
            $table->unsignedInteger('km_delta')->default(0);
            $table->boolean('counts_km')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('open_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('tire_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->foreignId('from_unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('from_position_id')->nullable()->constrained('unit_positions')->nullOnDelete();
            $table->unsignedInteger('from_odometer')->nullable();
            $table->foreignId('to_unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('to_position_id')->nullable()->constrained('unit_positions')->nullOnDelete();
            $table->unsignedInteger('to_odometer')->nullable();
            $table->foreignId('from_base_id')->nullable()->constrained('bases')->nullOnDelete();
            $table->foreignId('to_base_id')->nullable()->constrained('bases')->nullOnDelete();
            $table->unsignedInteger('km_delta')->default(0);
            $table->boolean('counts_km')->default(false);
            $table->foreignId('reason_id')->nullable()->constrained('movement_reasons')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tire_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        Schema::create('unit_couplings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tractor_id')->constrained('fleet_units')->restrictOnDelete();
            $table->foreignId('trailer_id')->constrained('fleet_units')->restrictOnDelete();
            $table->unsignedInteger('tractor_odometer_start');
            $table->unsignedInteger('tractor_odometer_end')->nullable();
            $table->timestamp('coupled_at');
            $table->timestamp('uncoupled_at')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('open_trailer_key')->nullable()->unique();
            $table->unsignedBigInteger('open_tractor_key')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->unsignedInteger('value');
            $table->string('status');
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('validation_source')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('tire_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['unit_id', 'recorded_at']);
        });

        Schema::create('unit_configuration_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained('fleet_units')->restrictOnDelete();
            $table->foreignId('from_configuration_id')->constrained('unit_configurations')->restrictOnDelete();
            $table->foreignId('to_configuration_id')->constrained('unit_configurations')->restrictOnDelete();
            $table->string('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tire_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->timestamp('occurred_at');
            $table->foreignId('unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('unit_positions')->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('description')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tire_id', 'occurred_at']);
        });

        Schema::create('tire_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->timestamp('measured_at');
            $table->foreignId('unit_id')->nullable()->constrained('fleet_units')->nullOnDelete();
            $table->unsignedInteger('odometer')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('raises_alert')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tire_measurement_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_measurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('measurement_zone_id')->constrained()->restrictOnDelete();
            $table->decimal('millimeters', 5, 1);
            $table->unique(['tire_measurement_id', 'measurement_zone_id'], 'tmr_measurement_zone_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('tire_measurement_readings');
        Schema::dropIfExists('tire_measurements');
        Schema::dropIfExists('tire_incidents');
        Schema::dropIfExists('unit_configuration_changes');
        Schema::dropIfExists('odometer_readings');
        Schema::dropIfExists('unit_couplings');
        Schema::dropIfExists('tire_movements');
        Schema::dropIfExists('tire_assignment_segments');
        Schema::dropIfExists('tire_assignments');
        Schema::dropIfExists('tire_operations');
        Schema::dropIfExists('tire_current_locations');
        Schema::dropIfExists('tire_lifecycles');
        Schema::dropIfExists('tires');
        Schema::dropIfExists('tire_purchase_items');
        Schema::dropIfExists('tire_purchases');
        Schema::dropIfExists('fleet_units');
        Schema::dropIfExists('movement_reasons');
        Schema::dropIfExists('unit_positions');
        Schema::dropIfExists('unit_configurations');
        Schema::dropIfExists('unit_types');
        Schema::dropIfExists('measurement_zones');
        Schema::dropIfExists('tire_model_sizes');
        Schema::dropIfExists('tire_sizes');
        Schema::dropIfExists('tire_models');
        Schema::dropIfExists('tire_brands');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('user_base_access');
        Schema::dropIfExists('user_fleet_access');
        Schema::dropIfExists('fleet_base');
        Schema::dropIfExists('bases');
        Schema::dropIfExists('fleets');
    }
};
