<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tax_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('document_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document');
            $table->unsignedBigInteger('value')->default(0);
            $table->unique(['company_id', 'document']);
        });

        Schema::create('retread_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->foreignId('retread_shop_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('status');
            $table->decimal('cost', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ARS');
            $table->nullableMorphs('costable');
            $table->foreignId('tire_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['company_id', 'category']);
        });

        Schema::create('tire_number_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('from_number');
            $table->unsignedInteger('to_number');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $this->addCompanyId('users');
        $this->addCompanyId('fleets');
        $this->addCompanyId('bases');
        $this->addCompanyId('suppliers');
        $this->addCompanyId('fleet_units');
        $this->addCompanyId('tires');
        $this->addCompanyId('tire_purchases');

        Schema::table('tires', function (Blueprint $table) {
            $table->uuid('public_token')->nullable()->unique();
        });

        Schema::table('tire_purchase_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 12, 2)->nullable();
        });

        Schema::table('tire_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('open_tire_id')->nullable()->unique();
        });

        Schema::table('tire_assignment_segments', function (Blueprint $table) {
            $table->unsignedBigInteger('open_assignment_id')->nullable()->unique();
        });

        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Empresa demo',
            'slug' => 'demo',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['users', 'fleets', 'bases', 'suppliers', 'fleet_units', 'tires', 'tire_purchases'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereNull('company_id')->update(['company_id' => $companyId]);
            }
        }

        foreach (DB::table('tires')->whereNull('public_token')->get() as $tire) {
            DB::table('tires')->where('id', $tire->id)->update(['public_token' => (string) Str::uuid()]);
        }

        DB::table('tire_assignments')->whereNull('ended_at')->update([
            'open_tire_id' => DB::raw('tire_id'),
        ]);
        DB::table('tire_assignment_segments')->whereNull('ended_at')->update([
            'open_assignment_id' => DB::raw('tire_assignment_id'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('tire_number_changes');
        Schema::dropIfExists('cost_entries');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('retread_shops');
        Schema::dropIfExists('document_counters');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('companies');
    }

    private function addCompanyId(string $table): void
    {
        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'company_id')) {
            return;
        }
        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('company_id')->nullable()->constrained()->restrictOnDelete();
        });
    }
};
