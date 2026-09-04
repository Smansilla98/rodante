<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('tire_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('open_tire_id')->nullable()->unique();
            $table->timestamps();
            $table->unique(['work_order_id', 'tire_id']);
        });

        $now = now();
        DB::table('work_orders')->orderBy('id')->chunkById(200, function ($orders) use ($now) {
            $rows = [];
            foreach ($orders as $order) {
                $open = in_array($order->status, ['ABIERTA', 'EN_TALLER'], true);
                $rows[] = [
                    'work_order_id' => $order->id,
                    'tire_id' => $order->tire_id,
                    'open_tire_id' => $open ? $order->tire_id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows !== []) {
                DB::table('work_order_items')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_items');
    }
};
