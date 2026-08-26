<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 80)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('captured_at');
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'tire_id', 'kind']);
        });

        Schema::create('telemetry_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->string('source', 16);
            $table->string('path')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_events');
        Schema::dropIfExists('tire_photos');
    }
};
